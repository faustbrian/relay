<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

/**
 * Interface for protocol attributes (JSON-RPC, XML-RPC, SOAP, GraphQL).
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Protocol
{
    /**
     * Get the protocol identifier.
     */
    public function protocol(): string;

    /**
     * Get the default content type for this protocol.
     */
    public function defaultContentType(): string;
}
