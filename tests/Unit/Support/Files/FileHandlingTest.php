<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Files\File;
use Cline\Relay\Support\Files\FileCollection;
use GuzzleHttp\Psr7\Response as Psr7Response;

function createFileRequest(): Request
{
    return new #[Get()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/download';
        }
    };
}

describe('File', function (): void {
    describe('fromPath()', function (): void {
        it('creates file from path', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test');
            file_put_contents($tempFile, 'Hello, World!');

            try {
                $file = File::fromPath('document', $tempFile);

                expect($file->name())->toBe('document');
                expect($file->contents())->toBe('Hello, World!');
                expect($file->filename())->toBe(basename($tempFile));
                expect($file->size())->toBe(13);
            } finally {
                unlink($tempFile);
            }
        });

        it('accepts custom filename', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test');
            file_put_contents($tempFile, 'test');

            try {
                $file = File::fromPath('document', $tempFile, 'custom.txt');

                expect($file->filename())->toBe('custom.txt');
            } finally {
                unlink($tempFile);
            }
        });

        it('accepts custom mime type', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test');
            file_put_contents($tempFile, 'test');

            try {
                $file = File::fromPath('document', $tempFile, null, 'text/plain');

                expect($file->mimeType())->toBe('text/plain');
            } finally {
                unlink($tempFile);
            }
        });

        it('throws for non-existent file', function (): void {
            File::fromPath('document', '/nonexistent/file.txt');
        })->throws(InvalidArgumentException::class, 'File not found');

        it('guesses mime type from extension', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.json';
            file_put_contents($tempFile, '{}');

            try {
                $file = File::fromPath('document', $tempFile);

                expect($file->mimeType())->toBe('application/json');
            } finally {
                unlink($tempFile);
            }
        });
    });

    describe('fromContents()', function (): void {
        it('creates file from string contents', function (): void {
            $file = File::fromContents('data', 'binary data here', 'data.bin');

            expect($file->name())->toBe('data');
            expect($file->contents())->toBe('binary data here');
            expect($file->filename())->toBe('data.bin');
            expect($file->mimeType())->toBe('application/octet-stream');
        });

        it('accepts custom mime type', function (): void {
            $file = File::fromContents('data', '{}', 'data.json', 'application/json');

            expect($file->mimeType())->toBe('application/json');
        });
    });

    describe('fromBase64()', function (): void {
        it('creates file from base64 string', function (): void {
            $content = 'Hello, World!';
            $base64 = base64_encode($content);

            $file = File::fromBase64('encoded', $base64, 'message.txt');

            expect($file->contents())->toBe($content);
        });

        it('throws for invalid base64', function (): void {
            File::fromBase64('encoded', 'not-valid-base64!!!', 'file.txt');
        })->throws(InvalidArgumentException::class, 'Invalid base64');
    });

    describe('fromResource()', function (): void {
        it('creates file from stream resource', function (): void {
            $stream = fopen('php://temp', 'r+b');
            fwrite($stream, 'stream content');
            rewind($stream);

            $file = File::fromResource('stream', $stream, 'stream.txt');

            expect($file->contents())->toBe('stream content');

            fclose($stream);
        });
    });

    describe('immutable modifications', function (): void {
        it('withName creates copy with new name', function (): void {
            $file = File::fromContents('original', 'data', 'file.txt');
            $modified = $file->withName('renamed');

            expect($file->name())->toBe('original');
            expect($modified->name())->toBe('renamed');
        });

        it('withFilename creates copy with new filename', function (): void {
            $file = File::fromContents('doc', 'data', 'old.txt');
            $modified = $file->withFilename('new.txt');

            expect($file->filename())->toBe('old.txt');
            expect($modified->filename())->toBe('new.txt');
        });

        it('withMimeType creates copy with new mime type', function (): void {
            $file = File::fromContents('doc', 'data', 'file.txt');
            $modified = $file->withMimeType('text/plain');

            expect($file->mimeType())->toBe('application/octet-stream');
            expect($modified->mimeType())->toBe('text/plain');
        });

        it('withHeaders creates copy with additional headers', function (): void {
            $file = File::fromContents('doc', 'data', 'file.txt');
            $modified = $file->withHeaders(['X-Custom' => 'value']);

            expect($file->headers())->toBe([]);
            expect($modified->headers())->toBe(['X-Custom' => 'value']);
        });
    });

    describe('toMultipart()', function (): void {
        it('converts to Guzzle multipart format', function (): void {
            $file = File::fromContents('document', 'data', 'file.txt', 'text/plain');

            $multipart = $file->toMultipart();

            expect($multipart)->toHaveKey('name');
            expect($multipart)->toHaveKey('contents');
            expect($multipart)->toHaveKey('filename');
            expect($multipart['name'])->toBe('document');
            expect($multipart['contents'])->toBe('data');
            expect($multipart['filename'])->toBe('file.txt');
            expect($multipart['headers']['Content-Type'])->toBe('text/plain');
        });

        it('includes custom headers in multipart format', function (): void {
            $file = File::fromContents('document', 'data', 'file.txt')
                ->withHeaders(['X-Custom-Header' => 'custom-value', 'X-Another' => 'value']);

            $multipart = $file->toMultipart();

            expect($multipart['headers'])->toBe([
                'X-Custom-Header' => 'custom-value',
                'X-Another' => 'value',
                'Content-Type' => 'application/octet-stream',
            ]);
        });
    });

    describe('MIME type guessing', function (): void {
        it('guesses MIME type for JPEG images', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.jpg';
            file_put_contents($tempFile, 'fake image data');

            try {
                $file = File::fromPath('image', $tempFile);
                expect($file->mimeType())->toBe('image/jpeg');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for JPEG with jpeg extension', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.jpeg';
            file_put_contents($tempFile, 'fake image data');

            try {
                $file = File::fromPath('image', $tempFile);
                expect($file->mimeType())->toBe('image/jpeg');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for PNG images', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.png';
            file_put_contents($tempFile, 'fake image data');

            try {
                $file = File::fromPath('image', $tempFile);
                expect($file->mimeType())->toBe('image/png');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for GIF images', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.gif';
            file_put_contents($tempFile, 'fake image data');

            try {
                $file = File::fromPath('image', $tempFile);
                expect($file->mimeType())->toBe('image/gif');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for WebP images', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.webp';
            file_put_contents($tempFile, 'fake image data');

            try {
                $file = File::fromPath('image', $tempFile);
                expect($file->mimeType())->toBe('image/webp');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for SVG images', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.svg';
            file_put_contents($tempFile, '<svg></svg>');

            try {
                $file = File::fromPath('image', $tempFile);
                expect($file->mimeType())->toBe('image/svg+xml');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for PDF documents', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.pdf';
            file_put_contents($tempFile, 'fake pdf data');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('application/pdf');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for XML files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.xml';
            file_put_contents($tempFile, '<xml></xml>');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('application/xml');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for ZIP archives', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.zip';
            file_put_contents($tempFile, 'fake zip data');

            try {
                $file = File::fromPath('archive', $tempFile);
                expect($file->mimeType())->toBe('application/zip');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for GZ archives', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.gz';
            file_put_contents($tempFile, 'fake gz data');

            try {
                $file = File::fromPath('archive', $tempFile);
                expect($file->mimeType())->toBe('application/gzip');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for GZIP archives', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.gzip';
            file_put_contents($tempFile, 'fake gzip data');

            try {
                $file = File::fromPath('archive', $tempFile);
                expect($file->mimeType())->toBe('application/gzip');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for TAR archives', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.tar';
            file_put_contents($tempFile, 'fake tar data');

            try {
                $file = File::fromPath('archive', $tempFile);
                expect($file->mimeType())->toBe('application/x-tar');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for text files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.txt';
            file_put_contents($tempFile, 'text content');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('text/plain');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for HTML files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.html';
            file_put_contents($tempFile, '<html></html>');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('text/html');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for HTM files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.htm';
            file_put_contents($tempFile, '<html></html>');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('text/html');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for CSS files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.css';
            file_put_contents($tempFile, 'body { color: red; }');

            try {
                $file = File::fromPath('stylesheet', $tempFile);
                expect($file->mimeType())->toBe('text/css');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for JavaScript files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.js';
            file_put_contents($tempFile, 'console.log("test");');

            try {
                $file = File::fromPath('script', $tempFile);
                expect($file->mimeType())->toBe('application/javascript');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for CSV files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.csv';
            file_put_contents($tempFile, 'name,email');

            try {
                $file = File::fromPath('data', $tempFile);
                expect($file->mimeType())->toBe('text/csv');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for DOC files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.doc';
            file_put_contents($tempFile, 'fake doc data');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('application/msword');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for DOCX files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.docx';
            file_put_contents($tempFile, 'fake docx data');

            try {
                $file = File::fromPath('document', $tempFile);
                expect($file->mimeType())->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for XLS files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.xls';
            file_put_contents($tempFile, 'fake xls data');

            try {
                $file = File::fromPath('spreadsheet', $tempFile);
                expect($file->mimeType())->toBe('application/vnd.ms-excel');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for XLSX files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.xlsx';
            file_put_contents($tempFile, 'fake xlsx data');

            try {
                $file = File::fromPath('spreadsheet', $tempFile);
                expect($file->mimeType())->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for MP3 audio files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.mp3';
            file_put_contents($tempFile, 'fake mp3 data');

            try {
                $file = File::fromPath('audio', $tempFile);
                expect($file->mimeType())->toBe('audio/mpeg');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for MP4 video files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.mp4';
            file_put_contents($tempFile, 'fake mp4 data');

            try {
                $file = File::fromPath('video', $tempFile);
                expect($file->mimeType())->toBe('video/mp4');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for MOV video files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.mov';
            file_put_contents($tempFile, 'fake mov data');

            try {
                $file = File::fromPath('video', $tempFile);
                expect($file->mimeType())->toBe('video/quicktime');
            } finally {
                unlink($tempFile);
            }
        });

        it('guesses MIME type for AVI video files', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'test').'.avi';
            file_put_contents($tempFile, 'fake avi data');

            try {
                $file = File::fromPath('video', $tempFile);
                expect($file->mimeType())->toBe('video/x-msvideo');
            } finally {
                unlink($tempFile);
            }
        });
    });
});

describe('FileCollection', function (): void {
    it('can be created with files', function (): void {
        $file1 = File::fromContents('doc1', 'data1', 'file1.txt');
        $file2 = File::fromContents('doc2', 'data2', 'file2.txt');

        $collection = new FileCollection([$file1, $file2]);

        expect($collection->count())->toBe(2);
    });

    it('can add files fluently', function (): void {
        $collection = new FileCollection();

        $collection
            ->addFromContents('doc1', 'data1', 'file1.txt')
            ->addFromContents('doc2', 'data2', 'file2.txt');

        expect($collection->count())->toBe(2);
    });

    it('can add files from path', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test');

        try {
            $collection = new FileCollection();
            $collection->addFromPath('document', $tempFile);

            expect($collection->count())->toBe(1);
        } finally {
            unlink($tempFile);
        }
    });

    it('checks if empty', function (): void {
        $collection = new FileCollection();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isNotEmpty())->toBeFalse();

        $collection->addFromContents('doc', 'data', 'file.txt');

        expect($collection->isEmpty())->toBeFalse();
        expect($collection->isNotEmpty())->toBeTrue();
    });

    it('calculates total size', function (): void {
        $collection = new FileCollection();
        $collection
            ->addFromContents('doc1', 'hello', 'file1.txt') // 5 bytes
            ->addFromContents('doc2', 'world!', 'file2.txt'); // 6 bytes

        expect($collection->totalSize())->toBe(11);
    });

    it('converts to multipart array', function (): void {
        $collection = new FileCollection();
        $collection
            ->addFromContents('doc1', 'data1', 'file1.txt')
            ->addFromContents('doc2', 'data2', 'file2.txt');

        $multipart = $collection->toMultipart();

        expect($multipart)->toHaveCount(2);
        expect($multipart[0]['name'])->toBe('doc1');
        expect($multipart[1]['name'])->toBe('doc2');
    });

    it('is iterable', function (): void {
        $collection = new FileCollection();
        $collection->addFromContents('doc', 'data', 'file.txt');

        $count = 0;

        foreach ($collection as $file) {
            expect($file)->toBeInstanceOf(File::class);
            ++$count;
        }

        expect($count)->toBe(1);
    });

    it('returns all files as array', function (): void {
        $file1 = File::fromContents('doc1', 'data1', 'file1.txt');
        $file2 = File::fromContents('doc2', 'data2', 'file2.txt');

        $collection = new FileCollection([$file1, $file2]);

        $files = $collection->all();

        expect($files)->toBeArray();
        expect($files)->toHaveCount(2);
        expect($files[0])->toBe($file1);
        expect($files[1])->toBe($file2);
    });
});

describe('Response file downloads', function (): void {
    it('saves response to file', function (): void {
        $request = createFileRequest();
        $psrResponse = new Psr7Response(200, [], 'file contents here');
        $response = new Response($psrResponse, $request);

        $tempFile = tempnam(sys_get_temp_dir(), 'download');

        try {
            $response->saveTo($tempFile);

            expect(file_get_contents($tempFile))->toBe('file contents here');
        } finally {
            unlink($tempFile);
        }
    });

    it('throws when saving to non-existent directory', function (): void {
        $request = createFileRequest();
        $psrResponse = new Psr7Response(200, [], 'data');
        $response = new Response($psrResponse, $request);

        $response->saveTo('/nonexistent/directory/file.txt');
    })->throws(RuntimeException::class, 'Directory does not exist');

    it('returns body as stream', function (): void {
        $request = createFileRequest();
        $psrResponse = new Psr7Response(200, [], 'stream content');
        $response = new Response($psrResponse, $request);

        $stream = $response->stream();

        expect(is_resource($stream))->toBeTrue();
        expect(stream_get_contents($stream))->toBe('stream content');

        fclose($stream);
    });

    it('extracts filename from Content-Disposition', function (): void {
        $request = createFileRequest();
        $psrResponse = new Psr7Response(200, [
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ], '');
        $response = new Response($psrResponse, $request);

        expect($response->filename())->toBe('document.pdf');
    });

    it('extracts filename with RFC 5987 encoding', function (): void {
        $request = createFileRequest();
        $psrResponse = new Psr7Response(200, [
            'Content-Disposition' => "attachment; filename*=utf-8''report%202024.pdf",
        ], '');
        $response = new Response($psrResponse, $request);

        expect($response->filename())->toBe('report 2024.pdf');
    });

    it('returns null when no filename', function (): void {
        $request = createFileRequest();
        $psrResponse = new Psr7Response(200, [], '');
        $response = new Response($psrResponse, $request);

        expect($response->filename())->toBeNull();
    });

    it('detects file download response', function (): void {
        $request = createFileRequest();

        $downloadResponse = new Response(
            new Psr7Response(200, ['Content-Disposition' => 'attachment; filename="file.pdf"'], ''),
            $request,
        );

        $inlineResponse = new Response(
            new Psr7Response(200, ['Content-Disposition' => 'inline'], ''),
            $request,
        );

        $regularResponse = new Response(
            new Psr7Response(200, [], ''),
            $request,
        );

        expect($downloadResponse->isDownload())->toBeTrue();
        expect($inlineResponse->isDownload())->toBeFalse();
        expect($regularResponse->isDownload())->toBeFalse();
    });

    it('returns body as base64', function (): void {
        $request = createFileRequest();
        $content = 'binary content here';
        $psrResponse = new Psr7Response(200, [], $content);
        $response = new Response($psrResponse, $request);

        expect($response->base64())->toBe(base64_encode($content));
    });
});
