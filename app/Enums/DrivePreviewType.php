<?php

namespace App\Enums;

use App\Models\DriveItem;

/**
 * How the Drive viewer renders a file.
 *
 * Only MIME types browsers render safely inline are listed; everything
 * else falls back to a download card instead of being served inline.
 */
enum DrivePreviewType: string
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Video = 'video';
    case Audio = 'audio';
    case Text = 'text';
    case None = 'none';

    /**
     * @var array<int, string>
     */
    protected const array IMAGE_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/x-ms-bmp',
        'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon',
    ];

    /**
     * @var array<int, string>
     */
    protected const array VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

    /**
     * @var array<int, string>
     */
    protected const array AUDIO_MIME_TYPES = [
        'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/webm',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/flac', 'audio/x-flac',
    ];

    /**
     * @var array<int, string>
     */
    protected const array TEXT_MIME_TYPES = [
        'application/json', 'application/xml', 'application/x-yaml', 'application/yaml', 'application/javascript',
        'application/x-javascript', 'application/x-sh', 'application/x-httpd-php', 'application/x-php', 'application/sql',
        'application/toml', 'inode/x-empty',
    ];

    /**
     * Extensions shown as text even when their detected MIME type is generic.
     *
     * @var array<int, string>
     */
    protected const array TEXT_EXTENSIONS = [
        'txt', 'md', 'markdown', 'csv', 'tsv', 'log', 'json', 'xml', 'yml', 'yaml', 'toml', 'ini', 'conf', 'cfg', 'env',
        'properties', 'sh', 'bash', 'zsh', 'php', 'js', 'mjs', 'ts', 'jsx', 'tsx', 'vue', 'css', 'scss', 'html', 'htm',
        'sql', 'py', 'rb', 'go', 'rs', 'java', 'kt', 'c', 'h', 'cpp', 'hpp', 'cs', 'swift', 'lua', 'gitignore',
    ];

    /**
     * Determine how the given item can be previewed.
     */
    public static function for(DriveItem $item): self
    {
        if (! $item->isFile()) {
            return self::None;
        }

        $mimeType = strtolower((string) $item->mime_type);

        return match (true) {
            in_array($mimeType, self::IMAGE_MIME_TYPES, true) => self::Image,
            $mimeType === 'application/pdf' => self::Pdf,
            in_array($mimeType, self::VIDEO_MIME_TYPES, true) => self::Video,
            in_array($mimeType, self::AUDIO_MIME_TYPES, true) => self::Audio,
            str_starts_with($mimeType, 'text/'),
            in_array($mimeType, self::TEXT_MIME_TYPES, true),
            in_array((string) $item->extension, self::TEXT_EXTENSIONS, true) => self::Text,
            default => self::None,
        };
    }

    /**
     * Determine whether the file can be shown inside the viewer.
     */
    public function isPreviewable(): bool
    {
        return $this !== self::None;
    }
}
