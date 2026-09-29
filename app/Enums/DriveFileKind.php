<?php

namespace App\Enums;

use App\Models\DriveItem;

/**
 * The broad category of a Drive item, used for its icon and type label.
 */
enum DriveFileKind: string
{
    case Folder = 'folder';
    case Image = 'image';
    case Pdf = 'pdf';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Presentation = 'presentation';
    case Archive = 'archive';
    case Code = 'code';
    case Text = 'text';
    case Other = 'other';

    /**
     * @var array<string, array<int, string>>
     */
    protected const array EXTENSIONS = [
        'document' => ['doc', 'docx', 'odt', 'rtf', 'pages'],
        'spreadsheet' => ['xls', 'xlsx', 'ods', 'csv', 'tsv', 'numbers'],
        'presentation' => ['ppt', 'pptx', 'odp', 'key'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz'],
        'code' => [
            'php', 'js', 'mjs', 'ts', 'jsx', 'tsx', 'vue', 'css', 'scss', 'html', 'htm', 'json', 'xml', 'yml', 'yaml',
            'sql', 'py', 'rb', 'go', 'rs', 'java', 'kt', 'c', 'h', 'cpp', 'hpp', 'cs', 'swift', 'sh', 'lua',
        ],
    ];

    /**
     * Determine the kind of the given item from its type, MIME type and extension.
     */
    public static function for(DriveItem $item): self
    {
        if ($item->isFolder()) {
            return self::Folder;
        }

        $mimeType = strtolower((string) $item->mime_type);
        $extension = (string) $item->extension;

        foreach (self::EXTENSIONS as $kind => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return self::from($kind);
            }
        }

        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            $mimeType === 'application/pdf' => self::Pdf,
            str_starts_with($mimeType, 'video/') => self::Video,
            str_starts_with($mimeType, 'audio/') => self::Audio,
            DrivePreviewType::for($item) === DrivePreviewType::Text => self::Text,
            default => self::Other,
        };
    }

    /**
     * Get the display label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Folder => __('Folder'),
            self::Image => __('Image'),
            self::Pdf => __('PDF document'),
            self::Video => __('Video'),
            self::Audio => __('Audio'),
            self::Document => __('Document'),
            self::Spreadsheet => __('Spreadsheet'),
            self::Presentation => __('Presentation'),
            self::Archive => __('Archive'),
            self::Code => __('Code'),
            self::Text => __('Text'),
            self::Other => __('File'),
        };
    }

    /**
     * Get the Heroicon representing the kind.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Folder => 'folder',
            self::Image => 'photo',
            self::Pdf => 'document-text',
            self::Video => 'film',
            self::Audio => 'musical-note',
            self::Document => 'document-text',
            self::Spreadsheet => 'table-cells',
            self::Presentation => 'presentation-chart-bar',
            self::Archive => 'archive-box',
            self::Code => 'code-bracket',
            self::Text => 'document-text',
            self::Other => 'document',
        };
    }
}
