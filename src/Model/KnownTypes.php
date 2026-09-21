<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Model;

/**
 * Advisory wire-vocabulary lists, mirroring KNOWN_EVENT_TYPES
 * (guard_agent/models.py:13-53) and the SecurityMetric.metric_type Literal
 * (guard_agent/models.py:236-244). Event types are not validated: the server
 * accepts any `event_type` string. Metric types ARE validated.
 */
final class KnownTypes
{
    public const EVENT_TYPES = [
        'ip_banned',
        'ip_unbanned',
        'ip_blocked',
        'ip_ban_failed',
        'rate_limited',
        'rate_limit_script_reloaded',
        'suspicious_request',
        'cloud_blocked',
        'country_blocked',
        'penetration_attempt',
        'behavioral_violation',
        'user_agent_blocked',
        'custom_request_check',
        'decorator_violation',
        'decoding_error',
        'detection_engine_callback_error',
        'geo_lookup_failed',
        'https_enforced',
        'pattern_anomaly_slow_execution',
        'pattern_anomaly_timeout',
        'pattern_anomaly_statistical_anomaly',
        'redis_connection',
        'redis_error',
        'dynamic_rule_applied',
        'dynamic_rule_updated',
        'path_excluded',
        'route_unresolved',
        'pattern_detected',
        'pattern_added',
        'pattern_removed',
        'access_denied',
        'authentication_failed',
        'content_filtered',
        'emergency_mode_activated',
        'emergency_mode_block',
        'dynamic_rule_violation',
        'security_bypass',
        'security_headers_applied',
        'csp_violation',
    ];

    public const METRIC_TYPES = [
        'request_count',
        'response_time',
        'error_rate',
        'bandwidth_usage',
        'threat_level',
        'block_rate',
        'cache_hit_rate',
    ];

    private function __construct()
    {
    }
}
