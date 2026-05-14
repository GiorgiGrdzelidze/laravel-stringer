<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu;

use Stringer\Laravel\Telegram\Menu\Contracts\ChatStateStore;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuNode;
use Stringer\Laravel\Telegram\Menu\Contracts\MenuTranslator;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuContext;
use Stringer\Laravel\Telegram\Menu\DataObjects\MenuResult;

/**
 * Routes incoming Telegram messages through the menu tree.
 *
 * Lifecycle for each message:
 *
 *   1. Resolve the chat's current path from `ChatStateStore`.
 *   2. Walk the tree from root → current node.
 *   3. Find a child whose `buttonLabel($ctx)` matches the incoming text
 *      exactly (button presses send labels verbatim).
 *   4. If found:
 *        a. Call the child's `onSelected()`.
 *        b. If it returns a `MenuResult::navigate` → update the path and
 *           render the target node.
 *        c. If it returns any other `MenuResult` → render it as the action
 *           result, leaving the path unchanged.
 *        d. If it returns `null` → update the path and render the child
 *           as a navigation screen.
 *   5. If no match, return `RouterDecision::fallThrough` so the host's
 *      regular text-command dispatcher gets a chance.
 *
 * Special handling:
 *  - `← Back` button (universal label key `menu.back`) walks one level up
 *    from the current path.
 *  - `/start` and `/menu` always reset the chat to the root.
 *
 * The router intentionally has no opinions about WHICH commands trigger a
 * reset or about WHAT the root node is — those are wired by the host.
 */
final class MenuRouter
{
    /**
     * @param  callable(): MenuNode  $rootResolver  Closure that returns the root menu node.
     *                                              Lazy so the tree doesn't have to be
     *                                              constructed on every request.
     */
    public function __construct(
        private readonly ChatStateStore $state,
        private readonly MenuRenderer $renderer,
        private readonly MenuTranslator $translator,
        private $rootResolver,
        private readonly string $defaultLocale = 'en',
    ) {}

    /**
     * Entry point. Returns true when the router handled the message and
     * false when it didn't (so the caller can fall through to existing
     * command handlers).
     */
    public function handle(int $chatId, int $userId, string $messageText): bool
    {
        $messageText = trim($messageText);

        if ($messageText === '') {
            return false;
        }

        $locale = $this->state->language($chatId, $this->defaultLocale);
        $rootNode = ($this->rootResolver)();

        $isResetCommand = in_array($messageText, ['/start', '/menu'], true);

        if ($isResetCommand) {
            $this->state->setCurrentPath($chatId, $rootNode->key());
            $ctx = $this->makeContext($chatId, $userId, $locale, $messageText, $rootNode->key());
            $this->renderer->renderNode($rootNode, $ctx);

            return true;
        }

        $currentPath = $this->state->currentPath($chatId);
        $currentNode = $this->resolvePath($rootNode, $currentPath);

        if ($currentNode === null) {
            // Stale path (tree changed since last interaction). Reset.
            $this->state->setCurrentPath($chatId, $rootNode->key());

            return false;
        }

        $ctx = $this->makeContext($chatId, $userId, $locale, $messageText, $currentPath);

        // ← Back: walk one segment up. We compare label by translation
        // since the same back button appears on every non-root screen.
        if ($messageText === $this->translator->translate('menu.back', $locale)) {
            $this->goBack($chatId, $currentPath, $rootNode, $ctx);

            return true;
        }

        // First try matching against the current screen's children. If the
        // user is deep in the tree and taps a contextual button, that's
        // handled here.
        if ($this->matchChild($currentNode, $ctx, $currentPath, $rootNode)) {
            return true;
        }

        // Root-menu buttons should always be tappable, regardless of the
        // current screen — this fixes the race where the user taps a root
        // button before the bot has updated the keyboard from a previous
        // navigation. Skip when the current node IS root (already tried).
        if ($currentNode !== $rootNode) {
            $rootCtx = $ctx->withPath($rootNode->key());

            return $this->matchChild($rootNode, $rootCtx, $rootNode->key(), $rootNode);
        }

        return false;
    }

    private function matchChild(MenuNode $currentNode, MenuContext $ctx, string $currentPath, MenuNode $rootNode): bool
    {
        foreach ($currentNode->children($ctx) as $child) {
            if ($child->buttonLabel($ctx) !== $ctx->messageText) {
                continue;
            }

            $childPath = $currentPath.'.'.$child->key();
            $childCtx = $ctx->withPath($childPath);
            $result = $child->onSelected($childCtx);

            if ($result === null) {
                // Pure navigation node.
                $this->state->setCurrentPath($ctx->chatId, $childPath);
                $this->renderer->renderNode($child, $childCtx);

                return true;
            }

            if ($result->kind === MenuResult::KIND_NAVIGATE) {
                /** @var string $targetPath */
                $targetPath = $result->payload['path'];
                $targetNode = $this->resolvePath($rootNode, $targetPath);
                if ($targetNode !== null) {
                    $this->state->setCurrentPath($ctx->chatId, $targetPath);
                    $this->renderer->renderNode($targetNode, $childCtx->withPath($targetPath));
                }

                return true;
            }

            // Action result — render it but leave the path unchanged.
            $this->renderer->renderResult($result, $childCtx, $currentNode);

            return true;
        }

        return false;
    }

    private function goBack(int $chatId, string $currentPath, MenuNode $rootNode, MenuContext $ctx): void
    {
        $segments = explode('.', $currentPath);
        if (count($segments) <= 1) {
            // Already at root.
            $this->renderer->renderNode($rootNode, $ctx);

            return;
        }

        array_pop($segments);
        $parentPath = implode('.', $segments);

        $parentNode = $this->resolvePath($rootNode, $parentPath);
        if ($parentNode === null) {
            $this->state->setCurrentPath($chatId, $rootNode->key());
            $this->renderer->renderNode($rootNode, $ctx->withPath($rootNode->key()));

            return;
        }

        $this->state->setCurrentPath($chatId, $parentPath);
        $this->renderer->renderNode($parentNode, $ctx->withPath($parentPath));
    }

    /**
     * Walk the tree from the root to the node at `$path`. Returns null when
     * the path is stale (a child key referenced doesn't exist any more).
     */
    private function resolvePath(MenuNode $root, string $path): ?MenuNode
    {
        $segments = explode('.', $path);

        if ($segments === [] || $segments[0] !== $root->key()) {
            return null;
        }

        $node = $root;

        for ($i = 1; $i < count($segments); $i++) {
            $segment = $segments[$i];
            $found = null;
            $stubCtx = $this->makeContext(0, 0, $this->defaultLocale, '', $path);

            foreach ($node->children($stubCtx) as $child) {
                if ($child->key() === $segment) {
                    $found = $child;
                    break;
                }
            }

            if ($found === null) {
                return null;
            }

            $node = $found;
        }

        return $node;
    }

    private function makeContext(int $chatId, int $userId, string $locale, string $messageText, string $currentPath): MenuContext
    {
        return new MenuContext(
            chatId: $chatId,
            userId: $userId,
            locale: $locale,
            messageText: $messageText,
            currentPath: $currentPath,
            translator: $this->translator,
        );
    }
}
