<?php

declare(strict_types=1);

namespace LongEssayPDFConverter;

/**
 * Convert PDF's to images.
 */
interface PDFImage
{
    public const THUMBNAIL = 'thumbnail';
    public const NORMAL = 'normal';

    /**
     * Returns an image for each page in the given PDF.
     *
     * @param resource $pdf
     * @return list<ImageDescriptor>
     */
    public function asOnePerPage($pdf, string $size = PDFImage::THUMBNAIL): array;

    /**
     * Returns an image of all PDF pages appended below each other.
     *
     * @param resource $pdf
     */
    public function asOne($pdf, string $size = PDFImage::THUMBNAIL): ImageDescriptor;
}
