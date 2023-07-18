<?php

declare(strict_types=1);

namespace LongEssayPDFConverter\Tests\ImageMagick;

use PHPUnit\Framework\TestCase;
use LongEssayPDFConverter\ImageMagick\PDFImage;
use Imagick;

class PDFImageTest extends TestCase
{
    public function testConstruct(): void
    {
        $this->assertInstanceOf(PDFImage::class, new PDFImage());
    }

    /**
     * @dataProvider sizeProvider
     */
    public function testAsOnePerPage(int $width, int $height, ...$args): void
    {
        $pdf_image = new PDFImage();

        $pdf = fopen($this->dummyPDF(), 'r');
        $fds = $pdf_image->asOnePerPage($pdf, ...$args);
        fclose($pdf);

        $this->assertSame(2, count($fds));
        foreach ($fds as $fd) {
            $this->assertPNGOfSize($fd, $width, $height);
            fclose($fd);
        }
    }

    /**
     * @dataProvider sizeProvider
     */
    public function testAsOne(int $width, int $height, ...$args): void
    {
        $pdf = fopen($this->dummyPDF(), 'r');
        $fd = (new PDFImage())->asOne($pdf, ...$args);
        fclose($pdf);

        $this->assertPNGOfSize($fd, $width, $height * 2);
    }

    public function sizeProvider(): array
    {
        return [
            'Test without explicit size.' => [827, 1169],
            'Test with normal size.' => [827, 1169, PDFImage::NORMAL],
            'Test with thumbnail size.' => [99, 140, PDFImage::THUMBNAIL],
        ];
    }

    private function assertPNGOfSize($fd, int $width, int $height): void
    {
        $magic = new Imagick();
        $magic->readImageFile($fd);

        $this->assertSame('PNG', $magic->identifyFormat('%m'));
        $this->assertSame($width, $magic->getImageWidth());
        $this->assertSame($height, $magic->getImageHeight());
    }

    private function dummyPDF(): string
    {
        // PDF with 2 pages.
        return __DIR__ . '/Test.pdf';
    }
}
