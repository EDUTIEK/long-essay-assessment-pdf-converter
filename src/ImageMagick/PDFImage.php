<?php

declare(strict_types=1);

namespace LongEssayPDFConverter\ImageMagick;

use LongEssayPDFConverter\PDFImage as PDFImageInterface;
use Imagick;
use Exception;

class PDFImage implements PDFImageInterface
{
    private string $output_format;

    public function __construct(string $output_format = 'PNG')
    {
        $this->assertSupportedFormat($output_format);
        $this->output_format = $output_format;
    }

    public function asOnePerPage($pdf, string $size = PDFImageInterface::NORMAL): array
    {
        return iterator_to_array($this->map([$this, 'pdfAsStream'], $this->prepare($pdf, $size)));
    }

    public function asOne($pdf, string $size = PDFImageInterface::NORMAL)
    {
        $magic = $this->prepare($pdf, $size);
        $magic = $magic->appendImages(true);

        return $this->pdfAsStream($magic);
    }

    private function prepare($pdf, string $size): Imagick
    {
        $magic = new Imagick();
        $magic->setOption('density', (string) $this->dpiOfSize($size));
        $magic->readImageFile($pdf);
        $magic->resetIterator();

        return $magic;
    }

    private function pdfAsStream(Imagick $magic)
    {
        // $magic->sharpenImage(0, 1);
        $magic->setImageCompressionQuality(100);

        $fd = fopen('php://temp', 'w+');
        $magic->writeImageFile($fd, $this->output_format);
        rewind($fd);

        return $fd;
    }

    private function assertSupportedFormat(string $format): void
    {
        if (!in_array($format, (new Imagick())->queryFormats(), true)) {
            throw new Exception('Image format "' . $format . '" is not supported by image magick.');
        }
    }

    private function map(callable $map, $iterable)
    {
        foreach ($iterable as $item) {
            yield $map($item);
        }
    }

    private function dpiOfSize(string $size): int
    {
        $dpi_map = [
            PDFImageInterface::NORMAL => 100,
            PDFImageInterface::THUMBNAIL => 12,
        ];

        if (!isset($dpi_map[$size])) {
            throw new Exception('Invalid size given: ' . $size);
        }

        return $dpi_map[$size];
    }
}
