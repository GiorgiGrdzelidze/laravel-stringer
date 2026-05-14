<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Commands;

use Stringer\Laravel\Telegram\ParsedCommand;

/**
 * Single-method handler interface for Telegram command classes.
 *
 * Implementations are resolved by `CommandDispatcher` and receive a
 * `ParsedCommand`. Side effects (queue mutation, reply send) happen
 * synchronously inside `handle()` — the controller acks the webhook
 * after dispatch returns.
 */
interface Command
{
    public function handle(ParsedCommand $command): void;
}
