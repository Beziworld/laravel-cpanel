<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Support\Values;

/**
 * Basic file manager operations of a cPanel account (UAPI Fileman module).
 */
final class Files extends Resource
{
    /**
     * Directory content: list of ['file' => ..., 'type' => 'file'|'dir', 'size' => ..., 'mtime' => ...].
     *
     * @param string $directory Relative to the home directory, e.g. "public_html".
     * @return list<array<array-key, mixed>>
     */
    public function list(string $directory, bool $showHidden = false): array
    {
        return self::rows($this->connection->uapi('Fileman', 'list_files', [
            'dir' => $directory,
            'show_hidden' => $showHidden,
            'include_mime' => false,
        ]));
    }

    /**
     * Returns the content of a text file.
     */
    public function get(string $directory, string $file): string
    {
        return Values::string(self::map($this->connection->uapi('Fileman', 'get_file_content', [
            'dir' => $directory,
            'file' => $file,
        ])), 'content');
    }

    /**
     * Creates or overwrites a text file.
     */
    public function put(string $directory, string $file, string $content): void
    {
        $this->connection->uapi('Fileman', 'save_file_content', [
            'dir' => $directory,
            'file' => $file,
            'content' => $content,
        ], write: true);
    }
}
