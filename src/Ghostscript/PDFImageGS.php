<?php

declare(strict_types=1);

namespace LongEssayPDFConverter\Ghostscript;

use LongEssayPDFConverter\PDFImage as PDFImageInterface;
use LongEssayPDFConverter\ImageDescriptor;
use Imagick;
use Exception;

class PDFImageGS implements PDFImageInterface
{
    private string $path_to_gs;
    private string $workdir;

    /**
     * Getting images from a pdf using ghostscript
     * @param string $path_to_gs        ghostscript executable
     * @param string $workdir           working directory
     * @throws Exception
     */
    public function __construct(string $path_to_gs, string $workdir)
    {
        $this->assertExecutable($path_to_gs);
        $this->assertDirectory($workdir);

        $this->path_to_gs = $path_to_gs;
        $this->workdir = $workdir;
    }

    /**
     * Returns an image for each page in the given PDF.
     *
     * @param resource $pdf
     * @return list<ImageDescriptor>
     */
    public function asOnePerPage($pdf, string $size = PDFImageInterface::NORMAL): array
    {
        $meta = stream_get_meta_data($pdf);
        $inputFile = $meta['uri'];
        $this->assertFile($inputFile);

        // use '#' instead of '%' as it gets replaced by 'escapeshellarg' on windows!
        $outputDir = rtrim($this->workdir, '/') . '/pdf2jpg_'. bin2hex(random_bytes(8));
        mkdir($outputDir);
        $outputFile = $outputDir . "/#04d.jpg";

        // create images with ghostscript
        $args = sprintf(
            "-dBATCH -dNOPAUSE -dSAFER -sDEVICE=jpeg -dJPEGQ=90 -r%s -o %s %s",
            $this->dpiOfSize($size),
            str_replace("#", "%", escapeshellarg($outputFile)),
            escapeshellarg($inputFile)
        );

        $output = [];
        $result_code = null;
        $command = escapeshellcmd($this->path_to_gs) . ' ' . $args;
        exec($command, $output, $result_code);

        $images = [];
        foreach (glob($outputDir . '/*.jpg') as $file) {
            $magic = new Imagick($file);
            $height = $magic->getImageHeight();
            $width = $magic->getImageWidth();
            unset($magic);

            $number = (int) basename($file, 'jpg');
            $fp = fopen($file, 'r');

            $images[$number] = new ImageDescriptor(
              $fp,
              $width,
              $height,
              'image/jpeg'
            );
        }

        return $images;
    }

    public function asOne($pdf, string $size = PDFImageInterface::NORMAL): ?ImageDescriptor
    {
        return null;
    }

    private function assertFile($path) {
        if (!is_file($path)) {
            throw new Exception('File Path "' . $path . '" is not a file');
        }
    }

    private function assertExecutable($path): void{
        if (!is_executable($path)) {
            throw new Exception('Ghostscript Path "' . $path . '" is not executable');
        }
    }
    private function assertDirectory($path): void{
        if (!is_dir($path)) {
            throw new Exception('Working directory "' . $path . '" is not a directory');
        }
    }

    private function dpiOfSize(string $size): int
    {
        $dpi_map = [
            PDFImageInterface::NORMAL => 300,
            PDFImageInterface::THUMBNAIL => 30,
        ];

        if (!isset($dpi_map[$size])) {
            throw new Exception('Invalid size given: ' . $size);
        }

        return $dpi_map[$size];
    }
}
