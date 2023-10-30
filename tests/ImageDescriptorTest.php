<?php

declare(strict_types=1);

namespace LongEssayPDFConverter\Tests;

use PHPUnit\Framework\TestCase;
use LongEssayPDFConverter\ImageDescriptor;

class ImageDescriptorTest extends TestCase
{
    public function testConstruct(): void
    {
        $this->assertInstanceOf(ImageDescriptor::class, new ImageDescriptor(0, 100, 200, 'foo'));
    }

    public function testGetters(): void
    {
        $stream = fopen('php://memory', 'r');
        $instance = new ImageDescriptor($stream, 100, 200, 'foo');
        $this->assertSame($stream, $instance->stream());
        $this->assertSame(100, $instance->width());
        $this->assertSame(200, $instance->height());
        $this->assertSame('foo', $instance->type());
        fclose($stream);
    }
}
