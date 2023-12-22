<?php

namespace App\Controller;


use App\Entity\Result;
use App\Entity\User;
use App\Repository\ResultRepository;
use App\Utility\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class ApiUsersController
 *
 * @package App\Controller
 *
 * @Route(
 *     path=ApiResultsQueryInterface::RUTA_API,
 *     name="api_results_"
 * )
 */

class ApiResultsQueryController  extends AbstractController implements ApiResultsQueryInterface
{
    private const HEADER_CACHE_CONTROL = 'Cache-Control';
    private const HEADER_ETAG = 'ETag';
    private const HEADER_ALLOW = 'Allow';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResultRepository $resultRepository,
    ) {
    }

    /**
     * @see ApiResultsQueryInterface::cgetAction()
     *
     * @Route(
     *     path=".{_format}/{sort?id}",
     *     defaults={ "_format": "json", "sort": "id" },
     *     requirements={
     *         "sort": "id|email|roles",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="cget"
     * )
     *
     * @throws JsonException
     */
    public function cgetAction(Request $request): Response
    {
        $format = Utils::getFormat($request);
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                '`Unauthorized`: Invalid credentials.',
                $format
            );
        }

        $order = strval($request->get('sort'));
        $results = $this->entityManager
            ->getRepository(Result::class)
            ->findBy([], [$order => 'ASC']);
        if (empty($results)) {
            return Utils::errorMessage(Response::HTTP_NOT_FOUND, null, $format);
        }
        $etag = md5((string) json_encode($results, JSON_THROW_ON_ERROR));
        if (($etags = $request->getETags()) && (in_array($etag, $etags) || in_array('*', $etags))) {
            return new Response(null, Response::HTTP_NOT_MODIFIED);
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ['results' => array_map(fn($result) => ['result' => $result], $results)],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * @Route(
     *     path=".{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_POST },
     *     name="post"
     * )
     * @throws JsonException
     */
    public function postAction(Request $request): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                '`Unauthorized`: Invalid credentials.',
                $format
            );
        }

        $body = $request->getContent();
        $postData = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($postData[Result::RESULT_ATTR], $postData[Result::TIME_ATTR], $postData['user'])) {
            return Utils::errorMessage(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Missing data: The result field, time field, or user field are not passed',
                $format
            );
        }

        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy([User::EMAIL_ATTR => $postData['user']]);

        if (!$user instanceof User) {
            return Utils::errorMessage(
                Response::HTTP_BAD_REQUEST,
                'User with the specified identifier does not exist',
                $format
            );
        }

        $result = new Result();
        $result->setResult($postData[Result::RESULT_ATTR]);

        if ($user !== null) {
            $result->setUser($user);
        }

        if (isset($postData[Result::TIME_ATTR])) {
            $result->setTimeFromString($postData[Result::TIME_ATTR]);
        }

        $this->resultRepository->insert($result);
        $this->entityManager->flush();

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            [ Result::RESULT_ATTR => $result ],
            $format,
            [
                'Location' => $request->getScheme() . '://' . $request->getHttpHost() .
                    self::RUTA_API . '/' . $result->getId(),
            ]
        );
    }

    /**
     * @see ApiResultsCommandInterface::deleteAction()
     *
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *         "resultId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_DELETE },
     *     name="delete"
     * )
     */
    public function deleteAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                '`Unauthorized`: Invalid credentials.',
                $format
            );
        }
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        if (!$result instanceof Result) {
            return Utils::errorMessage(
                Response::HTTP_NOT_FOUND,
                'Result not found',
                $format
            );
        }
        $user = $this->getUser();
        if (!$this->canDealResult($user, $result)) {
            return Utils::errorMessage(
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you dont have permission to delete this result',
                $format
            );
        }
        $this->resultRepository->remove($result);
        return Utils::apiResponse(
            Response::HTTP_NO_CONTENT,
            null,
            $format
        );
    }

    /**
     * @see ApiResultsCommandInterface::putAction()
     *
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "_format": "json" },
     *     requirements={ "resultId": "\d+", "_format": "json|xml" },
     *     methods={ Request::METHOD_PUT },
     *     name="put"
     * )
     * @throws JsonException
     */
    public function putAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                '`Unauthorized`: Invalid credentials.',
                $format
            );
        }
        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);
        if (!$result instanceof Result) {
            return Utils::errorMessage(
                Response::HTTP_NOT_FOUND,
                'Result not found',
                $format
            );
        }
        $user = $this->getUser();
        if (!$this->canDealResult($user, $result)) {
            return Utils::errorMessage(
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you dont have permission to update this result',
                $format
            );
        }
        $body = (string) $request->getContent();
        $postData = json_decode($body, true);

        if (isset($postData[Result::RESULT_ATTR])) {
            $result->setResult($postData[Result::RESULT_ATTR]);
        }
        if (isset($postData[Result::TIME_ATTR])) {
            $result->setTimeFromString($postData[Result::TIME_ATTR]);
        }

        $this->entityManager->flush();
        return Utils::apiResponse(
            Response::HTTP_OK,
            [Result::RESULT_ATTR => $result],
            $format
        );
    }

    /**
     * @see ApiResultsQueryInterface::getAction()
     *
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "_format": "json" },
     *     requirements={ "resultId": "\d+", "_format": "json|xml" },
     *     methods={ Request::METHOD_GET },
     *     name="get"
     * )
     * @throws JsonException
     */
    public function getAction(Request $request, int $resultId): Response
    {
        $format = Utils::getFormat($request);

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return Utils::errorMessage(
                Response::HTTP_UNAUTHORIZED,
                '`Unauthorized`: Invalid credentials.',
                $format
            );
        }

        $result = $this->entityManager
            ->getRepository(Result::class)
            ->find($resultId);

        if (!$result instanceof Result) {
            return Utils::errorMessage(
                Response::HTTP_NOT_FOUND,
                'Result not found',
                $format
            );
        }

        if (!$this->canDealResult($this->getUser(), $result)) {
            return Utils::errorMessage(
                Response::HTTP_FORBIDDEN,
                '`Forbidden`: you dont have permission to get this result',
                $format
            );
        }

        $etag = md5(json_encode($result, JSON_THROW_ON_ERROR));

        if (($etags = $request->getETags()) && (in_array($etag, $etags) || in_array('*', $etags))) {
            return new Response(null, Response::HTTP_NOT_MODIFIED); // 304
        }
        return Utils::apiResponse(
            Response::HTTP_OK,
            [Result::RESULT_ATTR => $result],
            $format,
            [
                self::HEADER_CACHE_CONTROL => 'private',
                self::HEADER_ETAG => $etag,
            ]
        );
    }

    /**
     * @see ApiResultsQueryInterface::optionsAction()
     *
     * @Route(
     *     path="/{resultId}.{_format}",
     *     defaults={ "resultId" = 0, "_format": "json" },
     *     requirements={
     *          "resultId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_OPTIONS },
     *     name="options"
     * )
     */
    public function optionsAction(int|null $resultId): Response
    {
        $methods = $resultId !== 0
            ? [Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE]
            : [Request::METHOD_GET, Request::METHOD_POST];
        $methods[] = Request::METHOD_OPTIONS;

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            [
                self::HEADER_ALLOW => implode(',', $methods),
                self::HEADER_CACHE_CONTROL => 'public, immutable'
            ]
        );
    }

    /**
     * Check if the user has the necessary permissions to deal with result.
     *
     * @param UserInterface|null $user
     * @param Result $result
     * @return bool
     */
    private function canDealResult(?UserInterface $user, Result $result): bool
    {
        if ($user && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }
        return $user && $user->getId() === $result->getUser()->getId();
    }

}