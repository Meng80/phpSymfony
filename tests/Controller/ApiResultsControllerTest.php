<?php

namespace App\Tests\Controller;

use App\Entity\Result;
use App\Entity\User;
use Faker\Factory as FakerFactoryAlias;
use Generator;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ApiResultsControllerTest
 *
 * @package App\Tests\Controller
 * @group   controllers
 *
 * @coversDefaultClass \App\Controller\ApiResultsQueryController
 */
class ApiResultsControllerTest extends BaseTestCase
{

    private const RUTA_API = '/api/v1/results';
    /** @var array<string,string> $userHeaders */
    private static array $userHeaders;
    /** @var array<string,string> $adminHeaders */
    private static array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        self::$adminHeaders = self::getTokenHeaders(self::$role_admin[User::EMAIL_ATTR],self::$role_admin[User::PASSWD_ATTR]);
        self::$userHeaders = self::getTokenHeaders(self::$role_user[User::EMAIL_ATTR], self::$role_user[User::PASSWD_ATTR]);
    }

    /**
     * Test OPTIONS /results[/resultId] 204 No Content
     *
     * @covers ::__construct
     * @covers ::optionsAction
     * @return void
     */
    public function testOptionsResultAction204NoContent(): void
    {
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertNotEmpty($response->headers->get('Allow'));
        $resultId = self::$faker->numberBetween(1, 100);
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API . $resultId
        );

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertNotEmpty($response->headers->get('Allow'));
    }

    /**
     * Test POST /results 201 Created
     *
     * @return array result data
     */
    public function testPostResultAction201Created(): array
    {
        $faker = \Faker\Factory::create();
        $resultData = [
            Result::RESULT_ATTR => $faker->numberBetween($min = 1, $max = 100),
            Result::TIME_ATTR => '2023-12-12 10:10:10',
            Result::USER_ATTR => self::$role_user[User::EMAIL_ATTR],
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($resultData))
        );
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertJson($response->getContent());
        $result = json_decode($response->getContent(), true)[Result::RESULT_ATTR];
        self::assertNotEmpty($result['id']);
        self::assertNotEmpty($result[Result::TIME_ATTR]);

        return $result;
    }

    /**
     * Test GET /results 200 Ok
     *
     * @depends testPostResultAction201Created
     *
     * @return string ETag header
     */
    public function testCGetResultAction200Ok(): string
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API, [], [], self::$adminHeaders);
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());
        $r_body = strval($response->getContent());
        self::assertJson($r_body);
        $results = json_decode($r_body, true);
        self::assertArrayHasKey('results', $results);

        return (string) $response->getEtag();
    }

    /**
     * Test GET /results 304 NOT MODIFIED
     *
     * @param string $etag returned by testCGetResultAction200Ok
     *
     * @depends testCGetResultAction200Ok
     */
    public function testCGetResultAction304NotModified(string $etag): void
    {
        $headers = array_merge(
            self::$adminHeaders,
            ['HTTP_If-None-Match' => [$etag]]
        );

        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API,
            [],
            [],
            $headers
        );

        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    /**
     * Test GET /results/{resultId} 200 Ok
     *
     * @param array $result result returned by testPostResultAction201Created()
     * @return string ETag header
     * @depends testPostResultAction201Created
     */
    public function testGetResultAction200Ok(array $result): string
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotNull($response->getEtag());
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        $result_aux = json_decode($r_body, true)[Result::RESULT_ATTR];
        self::assertSame($result['id'], $result_aux['id']);

        return (string) $response->getEtag();
    }

    /**
     * Test GET /results/{resultId} 304 NOT MODIFIED
     *
     * @param  array $result result returned by testPostResultAction201Created()
     * @param  string $etag returned by testGetResultAction200Ok()
     * @return string Entity tag
     *
     * @depends testPostResultAction201Created
     * @depends testGetResultAction200Ok
     */
    public function testGetResultAction304NotModified(array $result, string $etag): string
    {
        $headers = array_merge(
            self::$adminHeaders,
            ['HTTP_If-None-Match' => [$etag]]
        );

        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/' . $result['id'], [], [], $headers);
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        return $etag;
    }

    /**
     * Test POST   /results 400 BAD_REQUEST time
     *
     * @return void
     */
    public function testPostResultAction400BadRequest(): void
    {
        $invalidResultData = [
            Result::RESULT_ATTR => '10',
            Result::USER_ATTR => self::$role_admin[User::EMAIL_ATTR],
            Result::TIME_ATTR => 'invalid_timestamp_format',

        ];
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($invalidResultData))
        );
        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_BAD_REQUEST
        );

    }

    /**
     * Test PUT /results/{resultId} 209 Content Returned
     *
     * @param   array $result result returned by testPostResultAction201Created()
     * @param   string $etag returned by testGetResultAction304NotModified()
     * @return  array modified result data
     * @depends testPostResultAction201Created
     * @depends testCGetResultAction304NotModified
     * @depends testGetResultAction304NotModified
     */
    public function testPutResultAction209ContentReturned(array $result, string $etag): array
    {
        $updatedResultData = [
            Result::RESULT_ATTR =>self::$faker->numberBetween(0, 100),
            Result::TIME_ATTR => self::$faker->time('Y-m-d H:i:s'),

        ];
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$adminHeaders,
                ['HTTP_If-Match' => $etag]
            ),
            strval(json_encode($updatedResultData))
        );

        $response = self::$client->getResponse();
        self::assertEquals(209, $response->getStatusCode());

        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        $result = json_decode(strval($r_body),true);
        self::assertEquals($result['id'],$result[Result::RESULT_ATTR]['id']);
        return $result;
    }

    /**
     * Test PUT /results/{resultId} 400 Bad Request for Non-Admin Updating Other User's Result ID
     *
     * @param array<string, mixed> $resultData Result data returned by testPutResultAction209ContentReturned()
     * @return void
     * @depends testPutResultAction209ContentReturned
     */
    public function testPutResultAction400BadRequest(array $resultData): void
    {
        var_dump($resultData);

        if (!isset($resultData['id'])) {
            self::fail('The $resultData array does not contain the expected "id" key.');
        }

        $newResultData = [
            Result::RESULT_ATTR => $resultData[Result::RESULT_ATTR],
            Result::TIME_ATTR => $resultData[Result::TIME_ATTR],
            'user' => [
                'id' => 999,
            ],
        ];


        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . "/results/{$resultData['id']}",
            [],
            [],
            self::$userHeaders,
            json_encode($newResultData)
        );

        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test PUT /results/{resultId} 412 PRECONDITION_FAILED
     *
     * @param array<string, string> $result result returned by testPutResultAction209ContentReturned()
     *
     * @return void
     * @depends testPutResultAction209ContentReturned
     */
    public function testPutResultAction412PreconditionFailed(array $result): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_PRECONDITION_FAILED);
    }

    /**
     * Test DELETE /results/{resultId} 204 No Content
     *
     * @return int resultId
     * @depends testPostResultAction201Created
     * @depends testPutResultAction209ContentReturned
     * @depends testPutResultAction412PreconditionFailed
     * @depends testPutResultAction403Forbidden
     * @depends testCGetResultAction200XmlOk
     */
    public function testDeleteResultAction204NoContent(array $result): int
    {
        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders,
        );
        $response = self::$client->getResponse();
        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertEmpty($response->getContent());

        return intval($result['id']);

        self::$client->request(
            Request::METHOD_DELETE,
            '/api/v1/results/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertEmpty($response->getContent());

        return intval($result['id']);
    }

    /**
     * Test POST /results 422 Unprocessable Entity
     *
     * @dataProvider resultProvider422
     * @depends testPutResultAction209ContentReturned
     */
    public function testPostResultAction422UnprocessableEntity(?string $result, ?string $time): void
    {
        $p_data = [
            Result::RESULT_ATTR => $result,
            Result::TIME_ATTR => $time
        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API . '/results',
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test GET    /results 401 UNAUTHORIZED
     * Test POST   /results 401 UNAUTHORIZED
     * Test GET    /results/{resultId} 401 UNAUTHORIZED
     * Test PUT    /results/{resultId} 401 UNAUTHORIZED
     * Test DELETE /results/{resultId} 401 UNAUTHORIZED
     *
     * @param string $method
     * @param string $uri
     * @dataProvider providerRoutes401
     * @return void
     */
    public function testResultStatus401Unauthorized(string $method, string $uri): void
    {
        self::$client->request(
            $method,
            $uri,
            [],
            [],
            [ 'HTTP_ACCEPT' => 'application/json' ]
        );
        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Test GET    /results/{resultId} 404 NOT FOUND
     * Test PUT    /results/{resultId} 404 NOT FOUND
     * Test DELETE /results/{resultId} 404 NOT FOUND
     *
     * @param string $method
     * @param int $resultId result id. Replace with a non-existent result ID for the test case.
     * @return void
     * @dataProvider providerResultRoutes404
     * @depends      testDeleteResultAction204NoContent
     */
    public function testResultStatus404NotFound(string $method, int $resultId): void
    {
        self::$client->request(
            $method,
            self::RUTA_API . '/' . $resultId,
            [],
            [],
            self::$adminHeaders
        );
        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Test POST /results 403 FORBIDDEN
     * Test PUT /results/{resultId} 403 FORBIDDEN
     * Test DELETE /results/{resultId} 403 FORBIDDEN
     *
     * @param string $method
     * @param string $uri
     * @dataProvider providerResultRoutes403
     * @return void
     */
    public function testResultStatus403Forbidden(string $method, string $uri): void
    {

        var_dump("Method: $method, URI: $uri");
        $resultData = [
            Result::RESULT_ATTR=>1,
            Result::USER_ATTR=>self::$role_admin[User::EMAIL_ATTR],
            Result::TIME_ATTR=>'2023-12-12 10:10:10'
        ];
        self::$client->request(
            $method,
            $uri,
            [],
            [],
            self::$userHeaders,
            strval(json_encode($resultData))
        );

        $this->checkResponseErrorMessage(
            self::$client->getResponse(),
            Response::HTTP_FORBIDDEN
        );


    }

    /**
     * Result provider (incomplete) -> 422 status code
     *
     * @return Generator result data [result, time]
     */
    public static function resultProvider422(): Generator
    {
        $faker = FakerFactoryAlias::create();

        $result = $faker->sentence();
        $time = $faker->dateTime()->format('Y-m-d H:i:s');

        yield 'no_result'  => [null, $time];
        yield 'no_time'    => [$result, null];
        yield 'nothing'    => [null, null];
    }

    /**
     * Route provider (expected status: 401 UNAUTHORIZED)
     *
     * @return Generator name => [ method, url ]
     */
    #[ArrayShape([
        'cgetAction401' => "array",
        'getAction401' => "array",
        'postAction401' => "array",
        'putAction401' => "array",
        'deleteAction401' => "array"
    ])]
    public static function providerRoutes401(): Generator
    {
        yield 'cgetAction401'   => [Request::METHOD_GET, self::RUTA_API];
        yield 'getAction401'    => [Request::METHOD_GET, self::RUTA_API . '/1'];
        yield 'postAction401'   => [Request::METHOD_POST, self::RUTA_API];
        yield 'putAction401'    => [Request::METHOD_PUT , self::RUTA_API . '/1'];
        yield 'deleteAction401' => [Request::METHOD_DELETE, self::RUTA_API . '/1'];
    }

    /**
     * Result route provider (expected status 404 NOT FOUND)
     *
     * @return Generator name => [ method ]
     */
    #[ArrayShape([
        'getResultAction404'    => "array",
        'putResultAction404'    => "array",
        'deleteResultAction404' => "array"
    ])]
    public static function providerResultRoutes404(): Generator
    {
        yield 'getResultAction404'    => [Request::METHOD_GET];
        yield 'putResultAction404'    => [Request::METHOD_PUT];
        yield 'deleteResultAction404' => [Request::METHOD_DELETE];
    }

    /**
     * Route provider (expected status: 403 FORBIDDEN)
     *
     * @return Generator name => [ method, url ]
     */
    #[ArrayShape([
        'postAction403' => "array",
        'putAction403' => "array",
        'deleteAction403' => "array"
    ])]
    public static function providerResultRoutes403(): Generator
    {
        yield 'postAction403'   => [ Request::METHOD_POST,   self::RUTA_API ];
        yield 'putAction403'    => [ Request::METHOD_PUT,    self::RUTA_API . '/1' ];
        yield 'deleteAction403' => [ Request::METHOD_DELETE, self::RUTA_API . '/1' ];
    }

}
