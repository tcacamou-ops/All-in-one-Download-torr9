<?php
namespace AllI1D\Tr4ker\Tests\Unit\Models;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Tr4ker\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ReflectionProperty;

class Tr4kerApiClientTest extends UnitTestCase {

	private function client_with_response( string $body, int $status = 200 ): Tr4kerApiClient {
		$handler = HandlerStack::create( new MockHandler( [ new Response( $status, [], $body ) ] ) );

		$api_client = new Tr4kerApiClient( 'secret-key' );
		$property   = new ReflectionProperty( Tr4kerApiClient::class, 'client' );
		$property->setAccessible( true );
		$property->setValue( $api_client, new Client( [ 'handler' => $handler ] ) );

		return $api_client;
	}

	private function torznab_xml( string $link, string $title, int $seeders ): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:torznab="http://torznab.com/schemas/2015/feed">
  <channel>
    <item>
      <title>{$title}</title>
      <link>{$link}</link>
      <torznab:attr name="seeders" value="{$seeders}" />
    </item>
  </channel>
</rss>
XML;
	}

	public function test_list_torrents_extracts_the_seeders_count_from_the_torznab_attr(): void {
		$client = $this->client_with_response( $this->torznab_xml( 'https://tr4ker.net/dl/1', 'Movie.Title.2024.1080p.FRENCH', 42 ) );

		$response = $client->listTorrents( [ 'type' => 'movie' ] );

		$this->assertNotNull( $response );
		$this->assertCount( 1, $response['torrents'] );
		$this->assertSame( 42, $response['torrents'][0]['seeders'] );
		$this->assertSame( 'Movie.Title.2024.1080p.FRENCH', $response['torrents'][0]['name'] );
		$this->assertSame( 'https://tr4ker.net/dl/1', $response['torrents'][0]['id'] );
	}

	public function test_list_torrents_defaults_seeders_to_zero_when_the_attr_is_absent(): void {
		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:torznab="http://torznab.com/schemas/2015/feed">
  <channel>
    <item>
      <title>No seeders attr</title>
      <link>https://tr4ker.net/dl/2</link>
    </item>
  </channel>
</rss>
XML;
		$client = $this->client_with_response( $xml );

		$response = $client->listTorrents( [ 'type' => 'movie' ] );

		$this->assertSame( 0, $response['torrents'][0]['seeders'] );
	}
}
