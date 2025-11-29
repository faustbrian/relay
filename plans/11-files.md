# File Handling

## Multipart Uploads

```php
#[Post, Multipart]
class UploadFile extends Request
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $description,
    ) {}

    public function endpoint(): string
    {
        return '/files';
    }

    public function body(): ?array
    {
        return [
            'description' => $this->description,
            'file' => Attachment::fromPath($this->filePath),
        ];
    }
}

// Multiple files
public function body(): ?array
{
    return [
        'documents' => [
            Attachment::fromPath('/path/to/doc1.pdf'),
            Attachment::fromPath('/path/to/doc2.pdf'),
        ],
    ];
}

// From stream (memory efficient for large files)
public function body(): ?array
{
    return [
        'file' => Attachment::fromStream(
            fopen('/path/to/large-file.zip', 'r'),
            filename: 'large-file.zip',
            mimeType: 'application/zip',
        ),
    ];
}

// From string content
public function body(): ?array
{
    return [
        'file' => Attachment::fromContent(
            $csvContent,
            filename: 'data.csv',
            mimeType: 'text/csv',
        ),
    ];
}
```

## Response Streaming

Guzzle supports streaming out of the box — we expose it cleanly:

```php
// Stream large file download
#[Get, Json, Stream]
class DownloadFile extends Request
{
    public function endpoint(): string
    {
        return '/files/large-export.zip';
    }
}

$response = $connector->send(new DownloadFile());

// Stream to file
$response->streamTo('/path/to/save.zip');

// Stream with progress callback
$response->streamTo('/path/to/save.zip', function (int $downloaded, int $total) {
    $percent = $total > 0 ? ($downloaded / $total) * 100 : 0;
    echo "Downloaded: {$percent}%\n";
});

// Read chunks manually
foreach ($response->stream() as $chunk) {
    // Process chunk
}

// Server-Sent Events (SSE)
#[Get, Stream]
class EventStream extends Request
{
    public function endpoint(): string
    {
        return '/events';
    }
}

foreach ($connector->send(new EventStream())->events() as $event) {
    $event->name;  // event name
    $event->data;  // event data
    $event->id;    // event id
}
```
