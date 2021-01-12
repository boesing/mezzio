<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Handler;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Laminas\Diactoros\Uri;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NotFoundHandlerTest extends TestCase
{
    /** @var ResponseInterface&MockObject */
    private $response;

    /** @var callable */
    private $responseFactory;

    public function setUp() : void
    {
        $this->response = $this->createMock(ResponseInterface::class);
        $this->responseFactory = function () {
            return $this->response;
        };
    }

    public function testImplementsRequesthandler() : void
    {
        $handler = new NotFoundHandler($this->responseFactory);
        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);
    }

    public function testConstructorDoesNotRequireARenderer() : void
    {
        $handler = new NotFoundHandler($this->responseFactory);
        $this->assertInstanceOf(NotFoundHandler::class, $handler);
    }

    public function testConstructorCanAcceptRendererAndTemplate() : void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $template = 'foo::bar';
        $layout = 'layout::error';

        $handler = new NotFoundHandler($this->responseFactory, $renderer, $template, $layout);

        $this->assertInstanceOf(NotFoundHandler::class, $handler);
        $this->assertAttributeSame($renderer, 'renderer', $handler);
        $this->assertAttributeEquals($template, 'template', $handler);
        $this->assertAttributeEquals($layout, 'layout', $handler);
    }

    public function testRendersDefault404ResponseWhenNoRendererPresent() : void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn(RequestMethod::METHOD_POST);
        $request->method('getUri')->willReturn(new Uri('https://example.com/foo/bar'));

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())->method('write')->with('Cannot POST https://example.com/foo/bar');
        $this->response->method('withStatus')->with(StatusCode::STATUS_NOT_FOUND)->willReturn($this->response);
        $this->response->method('getBody')->willReturn($stream);

        $handler = new NotFoundHandler($this->responseFactory);

        $response = $handler->handle($request);

        $this->assertSame($this->response, $response);
    }

    public function testUsesRendererToGenerateResponseContentsWhenPresent() : void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer
            ->method('render')
            ->with(
                NotFoundHandler::TEMPLATE_DEFAULT,
                [
                    'request' => $request,
                    'layout' => NotFoundHandler::LAYOUT_DEFAULT,
                ]
            )
            ->willReturn('CONTENT');

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())->method('write')->with('CONTENT');

        $this->response->method('withStatus')->with(StatusCode::STATUS_NOT_FOUND)->willReturn($this->response);
        $this->response->method('getBody')->willReturn($stream);

        $handler = new NotFoundHandler($this->responseFactory, $renderer);

        $response = $handler->handle($request);

        $this->assertSame($this->response, $response);
    }
}
