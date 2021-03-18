<?php


namespace JulesGraus\FaviconTracker;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackerService
{
    use LogTrait;

    private $defaultCache = ['vector' => '', 'trackingId' => -1, 'writeMode' => false];

    public static $ROUTE_TYPE = 'alphabethical';

    public function generateTrackingId() {
        Cache::increment('trackingId');
        return Cache::get('trackingId');
    }

    public function alphabethRoutes() {
        $routes = [];
        $start = ord('a');
        for($x = 0; $x < 26; $x++) $routes[] = chr($start + $x);
        return $routes;
    }

    public function numericRoutes() {
        $routes = [];
        for($x = 0; $x < 10; $x++) $routes[] = (string) $x;
        return $routes;
    }

    public function generateVectorFromId(int $trackingId)
    {
        $this->log('debug', 'Generating a set of routes (vector) for id '.$trackingId);
        $routes = $this->routes();

        $vector = [];

        //Use bitmasking with the & operator to determine which routes to use. Put those routes in $vector.
        $maxBitMaskValue = 1 << mb_strlen(implode("", $routes));
        $currentBitmaskValue = 1;
        while($currentBitmaskValue <= $maxBitMaskValue) {
            if($currentBitmaskValue & $trackingId) {
                $routeIndex = strlen(decbin($currentBitmaskValue));
                $route = $routes[$routeIndex - 1];
                $vector[] = $route;
                $this->log('debug', 'Bitmask value: '.decbin($currentBitmaskValue).' ('.$currentBitmaskValue.') matches part of tracking id value '.decbin($trackingId).' ('.$trackingId.')');
                $this->log('debug', 'Because the bitmask is '.$routeIndex.' characters long, we pick route '. ($routeIndex - 1) . ', with value "'.$route.'" as a route wich must return a favicon. To write a binary 1.');
            }
            $currentBitmaskValue = $currentBitmaskValue << 1;
        }

        $this->log('debug', 'Generated vector: '.implode(', ', $vector));
        return $vector;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function isTrackingRoute(Request $request): bool
    {
        $prefix = config('favicontracker.prefix');
        return substr($request->path(), 0, mb_strlen($prefix)) === $prefix;
    }

    /**
     * @return bool
     */
    public function debugModeEnabled(): bool
    {
        return config('favicontracker.debug', false);
    }

    /**
     * @return array
     */
    public function routes(): array
    {
        switch (self::$ROUTE_TYPE) {
            default:
            case 'alphabetical':
                return $this->alphabethRoutes();
            case 'numeric':
                return $this->numericRoutes();
        }
    }

    public function cache(string $key, int $trackingId, string $vector, bool $writeMode)
    {
        $this->log('debug','Storing tracking id ('.$trackingId.') and vector ('.$vector.') in cache using key: '.$key);
        Cache::put($key, ['trackingId' => $trackingId, 'vector' => $vector, 'writeMode' => $writeMode]);
    }

    public function getTrackingIdFromCache(string $key): ? int {
        $data = Cache::get($key, ['vector' => '', 'trackingId' => -1]);
        return $data['trackingId'];
    }

    public function getVectorFromCache(string $key): ? string {
        $data = Cache::get($key, $this->defaultCache);
        return $data['vector'];
    }

    public function inWriteMode(string $key): ? string {
        $data = Cache::get($key, $this->defaultCache);
        return $data['writeMode'];
    }

    public function clearCacheForKey(string $key): static {
        $this->log('debug', 'Cleared cache for key: '.($key));
        Cache::forget($key);
        return $this;
    }

    public function setIsWriteMode(string $key, bool $writeModeEnabled): static
    {
        $data = Cache::get($key, ['vector' => '', 'trackingId' => -1]);
        $data['writeMode'] = $writeModeEnabled;
        Cache::put($key, $data);
        return $this;
    }
}