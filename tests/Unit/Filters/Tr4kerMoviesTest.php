<?php
namespace AllI1D\Tr4ker\Tests\Unit\Filters;

use AllI1D\Tr4ker\Filters\Tr4kerFeedFetcher;
use AllI1D\Tr4ker\Filters\Tr4kerMovies;
use AllI1D\Tr4ker\Tests\UnitTestCase;
use AllI1D\Models\Repositories\FeedCatalogRepository;

class Tr4kerMoviesTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		FeedCatalogRepository::set_search_results( [] );
	}

	protected function tearDown(): void {
		FeedCatalogRepository::set_search_results( [] );
		parent::tearDown();
	}

	private function stub_common_wp_functions(): void {
		\Brain\Monkey\Functions\stubs( [ 'do_action' ] );
		\Brain\Monkey\Functions\expect( 'wp_upload_dir' )
			->andReturn( [ 'basedir' => sys_get_temp_dir() . '/tr4ker-movies-test' ] );
		\Brain\Monkey\Functions\expect( 'get_option' )
			->with( 'alli1d_tr4ker_api_key', '' )
			->andReturn( '' );
	}

	public function test_catalog_hit_skips_live_fetch_and_downloads_the_catalog_match(): void {
		FeedCatalogRepository::set_search_results( [
			[ 'provider' => 'tr4ker', 'id' => 'not-a-valid-url', 'title' => 'The Matrix', 'quality' => '1080p', 'language' => 'FRENCH', 'score' => 5, 'extra' => [] ],
		] );

		\Brain\Monkey\Functions\expect( 'apply_filters' )
			->with( 'alli1d_torrent_matches_title', true, \Mockery::any() )
			->once()
			->andReturn( true );
		\Brain\Monkey\Functions\expect( 'apply_filters' )
			->with( 'alli1d_torrent_matches_quality', true, \Mockery::any() )
			->once()
			->andReturn( true );

		$this->stub_common_wp_functions();

		$fetcher = $this->createMock( Tr4kerFeedFetcher::class );
		$fetcher->expects( $this->never() )->method( 'get' );

		$movies = new Tr4kerMovies( $fetcher );
		$movie  = [ 'title' => 'The Matrix', 'audio_format' => 'VF', 'general_search_done' => false ];

		$result = $movies->process_movie( $movie );

		// The invalid torrent id makes downloadTorrent() fail fast without any
		// network call, so the download is reported as failed but the flow
		// still reached the download step — proving $items came from the
		// catalog match (the live fetcher above was asserted never called).
		$this->assertArrayNotHasKey( 'found', $result );
	}

	public function test_catalog_miss_and_general_search_not_done_runs_live_path(): void {
		FeedCatalogRepository::set_search_results( [] );

		\Brain\Monkey\Functions\expect( 'apply_filters' )
			->with( 'alli1d_torrent_matches_title', true, \Mockery::any() )
			->once()
			->andReturn( true );
		\Brain\Monkey\Functions\expect( 'apply_filters' )
			->with( 'alli1d_torrent_matches_quality', true, \Mockery::any() )
			->once()
			->andReturn( true );

		$this->stub_common_wp_functions();

		$fetcher = $this->createMock( Tr4kerFeedFetcher::class );
		$fetcher->expects( $this->once() )
			->method( 'get' )
			->with( [
				'context'      => 'cron',
				'type'         => 'movie',
				'title'        => 'Inception',
				'audio_format' => 'VF',
			] )
			->willReturn( [ [ 'id' => 'not-a-valid-url', 'title' => 'Inception' ] ] );

		$movies = new Tr4kerMovies( $fetcher );
		$movie  = [ 'title' => 'Inception', 'audio_format' => 'VF', 'general_search_done' => false ];

		$result = $movies->process_movie( $movie );

		// Live items are re-run through the same title-matching loop as
		// catalog candidates, so a matched torrent still reaches the
		// download step (which fails fast on the invalid id, no network).
		$this->assertArrayNotHasKey( 'found', $result );
	}

	public function test_catalog_miss_and_general_search_already_done_returns_early(): void {
		FeedCatalogRepository::set_search_results( [] );

		\Brain\Monkey\Functions\stubs( [ 'do_action' ] );
		\Brain\Monkey\Functions\expect( 'get_option' )->never();
		\Brain\Monkey\Functions\expect( 'wp_upload_dir' )->never();

		$fetcher = $this->createMock( Tr4kerFeedFetcher::class );
		$fetcher->expects( $this->never() )->method( 'get' );

		$movies = new Tr4kerMovies( $fetcher );
		$movie  = [ 'title' => 'Inception', 'audio_format' => 'VF', 'general_search_done' => true ];

		$result = $movies->process_movie( $movie );

		$this->assertSame( $movie, $result );
		$this->assertArrayNotHasKey( 'found', $result );
	}

	public function test_title_matching_but_quality_mismatched_candidate_is_skipped_for_the_next_one(): void {
		FeedCatalogRepository::set_search_results( [
			[ 'provider' => 'tr4ker', 'id' => 'candidate-720p', 'title' => 'The Matrix', 'quality' => '720p', 'language' => 'FRENCH', 'score' => 5, 'extra' => [] ],
			[ 'provider' => 'tr4ker', 'id' => 'not-a-valid-url', 'title' => 'The Matrix', 'quality' => '1080p', 'language' => 'FRENCH', 'score' => 5, 'extra' => [] ],
		] );

		$quality_calls = [];
		\Brain\Monkey\Functions\expect( 'apply_filters' )
			->andReturnUsing( function ( $filter, $default, $context ) use ( &$quality_calls ) {
				if ( 'alli1d_torrent_matches_title' === $filter ) {
					return true;
				}
				if ( 'alli1d_torrent_matches_quality' === $filter ) {
					$quality_calls[] = $context;
					// Reject the first (720p) candidate, accept the second (1080p) one.
					return count( $quality_calls ) > 1;
				}
				return $default;
			} );

		$this->stub_common_wp_functions();

		$fetcher = $this->createMock( Tr4kerFeedFetcher::class );
		$fetcher->expects( $this->never() )->method( 'get' );

		$movies = new Tr4kerMovies( $fetcher );
		$movie  = [ 'title' => 'The Matrix', 'audio_format' => 'VF', 'quality' => '1080p,2160p', 'general_search_done' => false ];

		$result = $movies->process_movie( $movie );

		// The 720p candidate is rejected on quality, the 1080p candidate
		// (the second, valid-looking id) is the one actually reaching the
		// download step.
		$this->assertArrayNotHasKey( 'found', $result );
		$this->assertCount( 2, $quality_calls, 'Both candidates should have been checked for quality.' );
		$this->assertSame( '720p', $quality_calls[0]['torrent_quality'] );
		$this->assertSame( '1080p', $quality_calls[1]['torrent_quality'] );
	}
}
