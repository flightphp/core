<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */
interface LegacyJsonSerializable
{
    /**
     * Gets the collection data which can be serialized to JSON.
     * @return mixed Collection data which can be serialized by <b>json_encode</b>
     */
    public function jsonSerialize();
}
