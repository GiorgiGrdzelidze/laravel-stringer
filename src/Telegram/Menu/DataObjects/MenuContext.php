<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\DataObjects;

use Stringer\Laravel\Telegram\Menu\Contracts\MenuTranslator;

/**
 * Request-scoped state passed into every `MenuNode` and `MenuAction`.
 *
 * Built by the router from the incoming Telegram update + the chat's stored
 * state. Immutable — actions return new states via `MenuResult` rather than
 * mutating the context.
 */
final readonly class MenuContext
{
    /**
     * @param  string  $messageText  The raw text the user sent. Either the
     *                               label of a button they tapped (reply
     *                               keyboards send button text as a regular
     *                               message) or free-form input.
     * @param  string  $currentPath  Where the user is in the menu tree right
     *                               now, dot-separated (e.g. `root.gen.cat`).
     * @param  array<string, mixed>  $pendingPayload  When the chat is in a
     *                                                pending-input flow, the payload carried
     *                                                by the trigger (e.g. `['category_slug' =>
     *                                                'backend']`). Empty otherwise.
     */
    public function __construct(
        public int $chatId,
        public int $userId,
        public string $locale,
        public string $messageText,
        public string $currentPath,
        public MenuTranslator $translator,
        public array $pendingPayload = [],
    ) {}

    /**
     * Helper for nodes: translate a key in the chat's current locale.
     *
     * @param  array<string, string|int|float>  $params
     */
    public function t(string $key, array $params = []): string
    {
        return $this->translator->translate($key, $this->locale, $params);
    }

    /**
     * Returns a new context with the path replaced. Used by the router when
     * advancing the chat to a new screen.
     */
    public function withPath(string $newPath): self
    {
        return new self(
            $this->chatId,
            $this->userId,
            $this->locale,
            $this->messageText,
            $newPath,
            $this->translator,
            $this->pendingPayload,
        );
    }
}
