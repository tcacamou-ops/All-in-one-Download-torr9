<?php
namespace AllI1D\Tr4ker\Tests\Unit\Filters;

use AllI1D\Tr4ker\Filters\Tr4kerFeedCatalogIndexer;
use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Tr4ker\Tests\UnitTestCase;

class Tr4kerFeedCatalogIndexerTest extends UnitTestCase {

	public function test_register_provider_appends_tr4ker_to_the_provider_list(): void {
		$indexer = new Tr4kerFeedCatalogIndexer();

		$this->assertSame( [ 'c411', 'tr4ker' ], $indexer->register_provider( [ 'c411' ] ) );
	}

	public function test_refresh_does_nothing_when_no_api_key_is_configured(): void {
		\Brain\Monkey\Functions\expect( 'get_option' )
			->once()
			->with( 'alli1d_tr4ker_api_key', '' )
			->andReturn( '' );

		\Brain\Monkey\Functions\expect( 'alli1d_index_feed_catalog' )->never();

		$indexer = new Tr4kerFeedCatalogIndexer();
		$indexer->refresh();
	}

	public function test_refresh_indexes_movie_and_tvshow_items_returned_by_the_client(): void {
		$movie_items  = [ [ 'provider' => 'tr4ker', 'title' => 'Movie', 'id' => 'https://tr4ker.net/dl/1', 'score' => 5, 'extra' => [] ] ];
		$tvshow_items = [ [ 'provider' => 'tr4ker', 'title' => 'Show', 'id' => 'https://tr4ker.net/dl/2', 'score' => 3, 'extra' => [] ] ];

		$client = $this->createMock( Tr4kerApiClient::class );
		$client->method( 'fetchFeed' )->willReturnMap( [
			[ [ 'type' => 'movie' ], $movie_items ],
			[ [ 'type' => 'tvshow' ], $tvshow_items ],
		] );

		\Brain\Monkey\Functions\expect( 'alli1d_index_feed_catalog' )
			->once()
			->with( 'tr4ker', 'movie', $movie_items );
		\Brain\Monkey\Functions\expect( 'alli1d_index_feed_catalog' )
			->once()
			->with( 'tr4ker', 'tvshow', $tvshow_items );

		$indexer = new Tr4kerFeedCatalogIndexer( $client );
		$indexer->refresh();
	}

	public function test_refresh_skips_indexing_a_type_when_the_client_returns_null(): void {
		$client = $this->createMock( Tr4kerApiClient::class );
		$client->method( 'fetchFeed' )->willReturn( null );

		\Brain\Monkey\Functions\expect( 'alli1d_index_feed_catalog' )->never();

		$indexer = new Tr4kerFeedCatalogIndexer( $client );
		$indexer->refresh();
	}
}
