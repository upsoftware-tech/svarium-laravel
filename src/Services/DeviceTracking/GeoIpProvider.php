<?php

namespace Upsoftware\Svarium\Services\DeviceTracking;

interface GeoIpProvider
{
    public function getCountry($ip);
    public function getCountryIsoCode($ip);
    public function getCity($ip);
    public function getState($ip);
    public function getCoord($ip);
}
