<?php

declare(strict_types=1);

namespace LongEssayPDFConverter\Ghostscript;

use LongEssayPDFConverter\PDFImage as PDFImageInterface;
use LongEssayPDFConverter\ImageDescriptor;
use Imagick;
use Exception;
use Iterator;
use DirectoryIterator;
use CallbackFilterIterator;
use SplFileInfo;

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

        $outputDir = rtrim($this->workdir, '/') . '/pdf2jpg_'. bin2hex(random_bytes(8));
        mkdir($outputDir);

        $this->gs($this->dpiOfSize($size), $inputFile, $outputDir . '/%04d.jpg');

        $images = [];
        foreach ($this->allFilesWithExtension($outputDir, 'jpg') as $file) {
            [$width, $height] = $this->getImageSizes($file->getPathname());

            $images[(int) $file->getBasename('.jpg')] = new ImageDescriptor(
                fopen($file->getPathname(), 'rb'),
                $width,
                $height,
                'image/jpeg'
            );
        }

        return $images;
    }

    public function asOne($pdf, string $size = PDFImageInterface::NORMAL): ImageDescriptor
    {
        $images = $this->asOnePerPage($pdf, $size);
        [$width, $height] = array_reduce($images, fn($s, $img) => [
            max($s[0], $img->width()),
            $s[1] + $img->height(),
        ], [0, 0]);

        $out = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $color);

        $prev_y = 0;
        foreach ($images as $image) {
            $file = stream_get_meta_data($image->stream())['uri'];
            $gd = imagecreatefromjpeg($file);
            imagecopymerge($out, $gd, 8, $prev_y, 0, 0, $image->width(), $image->height(), 100);
            $prev_y += $image->height();
            fclose($image->stream());
        }

        $out_stream = tmpfile();
        imagejpeg($out, $out_stream);
        rewind($out_stream);

        return new ImageDescriptor($out_stream, $width, $height, 'image/jpeg');
    }

    private function assertFile($path)
    {
        if (!is_file($path)) {
            throw new Exception('File Path "' . $path . '" is not a file');
        }
    }

    private function assertExecutable($path): void
    {
        if (!is_executable($path)) {
            throw new Exception('Ghostscript Path "' . $path . '" is not executable');
        }
    }

    private function assertDirectory($path): void
    {
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

    /**
     * Get Width and Height of an
     * @param string $file
     * @return array{int, int}
     */
    private function getImageSizes(string $file) : array
    {
        // prefer gd because of better resource usage
        if (extension_loaded('gd')) {
            $info = gd_info();
            if (!empty($info['JPEG Support'])) {
                $sizes = getimagesize($file);
                if (is_array($sizes)) {
                    return [(int) $sizes[0], (int) $sizes[1]];
                }
            }
        }

        // try imagick as fallback
        if (extension_loaded('imagick'))
        {
            $magic = new Imagick($file);
            $height = $magic->getImageHeight();
            $width = $magic->getImageWidth();
            return [$width, $height];
        }

        throw new Exception("Can't get image sizes of " . $file);
    }

    private function windowsSafeFilenameEscape(string $value): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return escapeshellarg($value);
        }

        if (false !== strpos($value, '|')) {
            throw new Exception('Pipes are not allowed in Windows file names.');
        }

        // Seems like escapeshellarg is broken for windows... % is removed instead of escaped.
        // % signs can be escaped with ^ in cmd.
        // Using | as a placeholder instead of #, because | is not allowed in Windows file names and it is not escaped by escapeshellarg.
        return str_replace('|', '^%', escapeshellarg(str_replace('%', '|', $value)));
    }

    private function gs(int $dpi, string $in_file, string $out_file): void
    {
        $command = sprintf(
            '%s %s -o %s %s',
            $this->path_to_gs,
            join(' ', array_map('escapeshellarg', $this->gsFlags($dpi))),
            $this->windowsSafeFilenameEscape($out_file),
            $this->windowsSafeFilenameEscape($in_file)
        );

        exec($command, $ignore_output, $exit_code);

        if ($exit_code) {
            throw new Exception('Error while executing Ghostscript command: ' . $command);
        }
    }

    /**
     * @return Iterator<SplFileInfo>
     */
    private function allFilesWithExtension(string $dir, string $extension): Iterator
    {
        return new CallbackFilterIterator(
            new DirectoryIterator($dir),
            fn(SplFileInfo $file) => $file->isFile() && $file->getExtension() === $extension
        );
    }

    private function gsFlags(int $dpi): array
    {
        // For custom sizes besides known ones: -g200x100 instead of -sPAPERSIZE=<FORMAT>
        return ["-r$dpi", '-dBATCH', '-dNOPAUSE', '-dSAFER', '-q', '-sDEVICE=jpeg', '-dJPEGQ=90', '-sPAPERSIZE=a4', '-dFitPage'];
    }
}
