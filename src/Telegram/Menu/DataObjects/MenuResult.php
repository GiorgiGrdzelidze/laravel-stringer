<?php

declare(strict_types=1);

namespace Stringer\Laravel\Telegram\Menu\DataObjects;

/**
 * Tagged union describing what the renderer should do next.
 *
 * Closed set — adding a new variant is a minor version bump. The renderer
 * uses `kind` to dispatch to the correct Telegram API endpoint.
 *
 * Variants:
 *  - `navigate`  → render the node at `$path` next.
 *  - `message`   → send `$text` (optionally with a keyboard) and stay on the
 *                  current path.
 *  - `photo`     → send `$photoUrl` with optional caption + keyboard.
 *  - `location`  → send a Telegram location pin.
 *  - `album`     → send a media group (multiple photos in one message).
 *  - `dismiss`   → close the menu (remove the reply keyboard); optional text.
 *
 * Helpers below are the only sanctioned way to construct a result.
 */
final readonly class MenuResult
{
    public const KIND_NAVIGATE = 'navigate';

    public const KIND_MESSAGE = 'message';

    public const KIND_PHOTO = 'photo';

    public const KIND_LOCATION = 'location';

    public const KIND_ALBUM = 'album';

    public const KIND_DISMISS = 'dismiss';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public string $kind,
        public array $payload,
    ) {}

    public static function navigate(string $path): self
    {
        return new self(self::KIND_NAVIGATE, ['path' => $path]);
    }

    public static function message(string $text, ?ReplyKeyboard $keyboard = null): self
    {
        return new self(self::KIND_MESSAGE, [
            'text' => $text,
            'keyboard' => $keyboard,
        ]);
    }

    public static function photo(string $url, ?string $caption = null, ?ReplyKeyboard $keyboard = null): self
    {
        return new self(self::KIND_PHOTO, [
            'url' => $url,
            'caption' => $caption,
            'keyboard' => $keyboard,
        ]);
    }

    public static function location(float $lat, float $lng, ?string $caption = null): self
    {
        return new self(self::KIND_LOCATION, [
            'lat' => $lat,
            'lng' => $lng,
            'caption' => $caption,
        ]);
    }

    /**
     * @param  list<string>  $photoUrls
     */
    public static function album(array $photoUrls, ?string $caption = null): self
    {
        return new self(self::KIND_ALBUM, [
            'urls' => $photoUrls,
            'caption' => $caption,
        ]);
    }

    public static function dismiss(?string $farewellText = null): self
    {
        return new self(self::KIND_DISMISS, ['text' => $farewellText]);
    }
}
