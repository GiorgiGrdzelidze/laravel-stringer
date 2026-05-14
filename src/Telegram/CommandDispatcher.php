<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram;

use Illuminate\Contracts\Container\Container;
use Stringer\Laravel\Telegram\Commands\EnqueueFreeTextCommand;
use Stringer\Laravel\Telegram\Commands\FileCommand;
use Stringer\Laravel\Telegram\Commands\HelpCommand;
use Stringer\Laravel\Telegram\Commands\ListCommand;
use Stringer\Laravel\Telegram\Commands\SpikeCommand;

/**
 * Routes a `ParsedCommand` to a concrete `Commands\Command` class.
 *
 * Unknown commands (e.g. `/whatever`) and non-command messages both
 * fall through to `EnqueueFreeTextCommand` — typing a question in the
 * bot should always result in a topic, never an "unknown command"
 * reply.
 */
final class CommandDispatcher
{
    /** @var array<string, class-string<Commands\Command>> */
    private const ROUTES = [
        'help' => HelpCommand::class,
        'list' => ListCommand::class,
        'file' => FileCommand::class,
        'draft' => FileCommand::class,
        'spike' => SpikeCommand::class,
    ];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function dispatch(ParsedCommand $command): void
    {
        $handler = self::ROUTES[$command->command ?? ''] ?? EnqueueFreeTextCommand::class;

        /** @var Commands\Command $instance */
        $instance = $this->container->make($handler);

        $instance->handle($command);
    }
}
