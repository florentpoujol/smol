<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\FileSystem;

use SplFileInfo;

final class FileInfo
{
    public function __construct(
        /**
         * @var string|resource|SplFileInfo $content
         */
        public $content,
        public string $path,
        public int $lastModified = 0,
        public string $mimeType = 'plain/text',
        public string $visibility = 'public',
    ) {
        if ($this->lastModified === 0) {
            $this->lastModified = time();
        }
    }

    /**
     * @return array<string, int|string>
     */
    public function getMetadata(): array
    {
        return [
            'lastModified' => $this->lastModified,
            'mimeType' => $this->mimeType,
            'visibility' => $this->visibility,
        ];
    }

    public function getContent(): string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        if (is_resource($this->content)) {
            rewind($this->content);

            return fread($this->content, 999_999_999);
        }

        assert($this->content instanceof SplFileInfo); // @phpstan-ignore-line (complain assert is redondant, but since the property has no typehint, it isn't)

        return file_get_contents($this->content->getRealPath());
    }
}
