<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Helpers\Crypto;

/**
 * Feeds the core's proactive catalog cron (`alli1d_refresh_feed_catalog`):
 * pulls the Tr4ker Torznab feed for each content type and pushes the
 * results into the shared catalog via `alli1d_index_feed_catalog()`.
 * Complementary to the on-demand search path (`Tr4kerSearch`/
 * `Tr4kerMovies`/`Tr4kerTvShows`), which is untouched by this class.
 *
 * `Tr4kerApiClient::fetchFeed()` already maps to the common catalog
 * contract; calling it with only a `type` (no `title`) leaves `q` empty —
 * `filter()` only applies a language filter, never a title match, so the
 * full active candidate list for that category comes back unfiltered,
 * exactly what the catalog needs.
 */
class Tr4kerFeedCatalogIndexer
{
    private const TYPES = ['movie', 'tvshow'];

    /** @var Tr4kerApiClient|null */
    private $client;

    public function __construct(?Tr4kerApiClient $client = null)
    {
        $this->client = $client;
    }

    public function refresh(): void
    {
        $client = $this->client ?? $this->build_client();
        if (null === $client) {
            return;
        }

        foreach (self::TYPES as $type) {
            $items = $client->fetchFeed(['type' => $type]);
            if (null === $items) {
                continue;
            }
            alli1d_index_feed_catalog('tr4ker', $type, $items);
        }
    }

    public function register_provider(array $providers): array
    {
        $providers[] = 'tr4ker';
        return $providers;
    }

    private function build_client(): ?Tr4kerApiClient
    {
        $api_key = Crypto::decrypt(get_option('alli1d_tr4ker_api_key', ''));
        if ('' === $api_key) {
            return null;
        }
        return new Tr4kerApiClient($api_key);
    }
}
