<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Thrown when an event/metric cannot be normalized into the wire model.
 * GuardAgent::sendEvent()/sendMetric() catch it, log it, and drop the item.
 */
final class InvalidEventException extends GuardAgentException
{
}
