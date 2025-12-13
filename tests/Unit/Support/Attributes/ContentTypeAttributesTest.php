<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Attributes\ContentTypes\Form;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\ContentTypes\Multipart;
use Cline\Relay\Support\Attributes\ContentTypes\Xml;
use Cline\Relay\Support\Attributes\ContentTypes\Yaml;

describe('Content Type Attributes', function (): void {
    it('Json returns application/json', function (): void {
        $attr = new Json();
        expect($attr->contentType())->toBe('application/json');
    });

    it('Form returns application/x-www-form-urlencoded', function (): void {
        $attr = new Form();
        expect($attr->contentType())->toBe('application/x-www-form-urlencoded');
    });

    it('Multipart returns multipart/form-data', function (): void {
        $attr = new Multipart();
        expect($attr->contentType())->toBe('multipart/form-data');
    });

    it('Xml returns application/xml', function (): void {
        $attr = new Xml();
        expect($attr->contentType())->toBe('application/xml');
    });

    it('Yaml returns application/x-yaml by default', function (): void {
        $attr = new Yaml();
        expect($attr->contentType())->toBe('application/x-yaml');
    });

    it('Yaml supports custom mime types', function (): void {
        $attr = new Yaml('text/yaml');
        expect($attr->contentType())->toBe('text/yaml');
    });
});
