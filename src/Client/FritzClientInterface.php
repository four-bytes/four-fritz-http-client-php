<?php

namespace Four\FritzHttpClient\Client;

/**
 * Interface for FritzBox API clients
 */
interface FritzClientInterface
{
    /**
     * Check if FritzBox is reachable
     */
    public function isReachable(): bool;
    
    /**
     * Authenticate with FritzBox
     */
    public function authenticate(): bool;
    
    /**
     * Get connection status
     */
    public function getStatus(): array;
}