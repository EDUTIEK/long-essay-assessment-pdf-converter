<?php

declare(strict_types=1);

namespace LongEssayPDFConverter;

class ImageDescriptor
{
    /** @var resource */
    private $stream;
    private int $width;
    private int $height;
    private string $type;

    /**
     * @param resource $stream
     */
    public function __construct($stream, int $width, int $height, string $type)
    {
        $this->stream = $stream;
        $this->width = $width;
        $this->height = $height;
        $this->type = $type;
    }

    /**
     * @return resource
     */
    public function stream()
    {
        return $this->stream;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function type(): string
    {
        return $this->type;
    }
}
