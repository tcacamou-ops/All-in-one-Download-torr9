<?php
namespace AllI1D\Tr4ker\Tests\Unit\Filters;

use AllI1D\Tr4ker\Filters\Tr4kerFeedFetcher;
use AllI1D\Tr4ker\Tests\UnitTestCase;

class Tr4kerFeedFetcherTest extends UnitTestCase {

	public function test_get_returns_null_when_api_key_is_empty(): void {
		\Brain\Monkey\Functions\expect( 'get_option' )
			->once()
			->with( 'alli1d_tr4ker_api_key', '' )
			->andReturn( '' );

		$fetcher = new Tr4kerFeedFetcher();
		$result  = $fetcher->get( [ 'context' => 'search', 'type' => 'movie', 'title' => 'Matrix' ] );

		$this->assertNull( $result );
	}
}
