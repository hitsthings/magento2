<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\Framework;

/**
 * Test class for Magento\Framework\Url
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UrlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Framework\Url\RouteParamsResolver|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $routeParamsResolverMock;

    /**
     * @var \Magento\Framework\Url\ScopeResolverInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $scopeResolverMock;

    /**
     * @var \Magento\Framework\Url\ScopeInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $scopeMock;

    /**
     * @var \Magento\Framework\Url\QueryParamsResolverInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $queryParamsResolverMock;

    /**
     * @var \Magento\Framework\Session\SidResolverInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $sidResolverMock;

    /**
     * @var \Magento\Framework\Session\Generic|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $sessionMock;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $scopeConfig;

    protected function setUp()
    {
        $this->routeParamsResolverMock = $this->getMock(
            'Magento\Framework\Url\RouteParamsResolver',
            ['getType', 'hasData', 'getData', 'getRouteParams'],
            [],
            '',
            false
        );
        $this->scopeResolverMock = $this->getMock('Magento\Framework\Url\ScopeResolverInterface');
        $this->scopeMock = $this->getMock('Magento\Framework\Url\ScopeInterface');
        $this->queryParamsResolverMock = $this->getMock(
            'Magento\Framework\Url\QueryParamsResolverInterface',
            [],
            [],
            '',
            false
        );
        $this->sidResolverMock = $this->getMock('Magento\Framework\Session\SidResolverInterface');
        $this->sessionMock = $this->getMock('Magento\Framework\Session\Generic', [], [], '', false);
        $this->scopeConfig = $this->getMock('\Magento\Framework\App\Config\ScopeConfigInterface');
    }

    /**
     * @return \Magento\Framework\Url\RouteParamsResolverFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRouteParamsResolver()
    {
        $routeParamsResolverFactoryMock = $this->getMock(
            'Magento\Framework\Url\RouteParamsResolverFactory',
            [],
            [],
            '',
            false
        );
        $routeParamsResolverFactoryMock->expects($this->once())->method('create')
            ->will($this->returnValue($this->routeParamsResolverMock));
        return $routeParamsResolverFactoryMock;
    }

    /**
     * @param array $mockMethods
     * @return \Magento\Framework\App\Request\Http|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRequestMock()
    {
        return $this->getMockBuilder('Magento\Framework\App\Request\Http')
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * @param array $arguments
     * @return \Magento\Framework\Url
     */
    protected function getUrlModel($arguments = [])
    {
        $arguments = array_merge($arguments, ['scopeType' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE]);
        $objectManager = new \Magento\TestFramework\Helper\ObjectManager($this);
        return $objectManager->getObject('Magento\Framework\Url', $arguments);
    }

    /**
     * @param $port mixed
     * @param $url string
     * @dataProvider getCurrentUrlProvider
     */
    public function testGetCurrentUrl($port, $url)
    {
        $requestMock = $this->getRequestMock();
        $requestMock->expects($this->at(0))->method('getServer')->with('SERVER_PORT')
            ->will($this->returnValue($port));
        $requestMock->expects($this->at(1))->method('getServer')->with('REQUEST_URI')
            ->will($this->returnValue('/fancy_uri'));
        $requestMock->expects($this->once())->method('getScheme')->will($this->returnValue('http'));
        $requestMock->expects($this->once())->method('getHttpHost')->will($this->returnValue('example.com'));

        $model = $this->getUrlModel(['request' => $requestMock]);
        $this->assertEquals($url, $model->getCurrentUrl());
    }

    public function getCurrentUrlProvider()
    {
        return [
            'without_port' => ['', 'http://example.com/fancy_uri'],
            'default_port' => [80, 'http://example.com/fancy_uri'],
            'custom_port' => [8080, 'http://example.com:8080/fancy_uri']
        ];
    }

    public function testGetUseSession()
    {
        $model = $this->getUrlModel();

        $model->setUseSession(false);
        $this->assertFalse((bool)$model->getUseSession());

        $model->setUseSession(true);
        $this->assertTrue($model->getUseSession());
    }

    public function testGetBaseUrlNotLinkType()
    {
        $model = $this->getUrlModel(
            ['scopeResolver' => $this->scopeResolverMock, 'routeParamsResolver' => $this->getRouteParamsResolver()]
        );

        $baseUrl = 'base-url';
        $urlType = 'not-link';
        $this->routeParamsResolverMock->expects($this->any())->method('getType')->will($this->returnValue($urlType));
        $this->scopeMock->expects($this->once())->method('getBaseUrl')->will($this->returnValue($baseUrl));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));

        $baseUrlParams = ['_scope' => $this->scopeMock, '_type' => $urlType, '_secure' => true];
        $this->assertEquals($baseUrl, $model->getBaseUrl($baseUrlParams));
    }

    public function testGetUrlValidateFilter()
    {
        $model = $this->getUrlModel();
        $this->assertEquals('http://test.com', $model->getUrl('http://test.com'));
    }

    /**
     * @param string|array|bool $query
     * @param string $queryResult
     * @param string $returnUri
     * @dataProvider getUrlDataProvider
     */
    public function testGetUrl($query, $queryResult, $returnUri)
    {
        $requestMock = $this->getRequestMock();
        $routeConfigMock = $this->getMock('Magento\Framework\App\Route\ConfigInterface');
        $model = $this->getUrlModel(
            ['scopeResolver' => $this->scopeResolverMock, 'routeParamsResolver' => $this->getRouteParamsResolver(),
                'queryParamsResolver' => $this->queryParamsResolverMock,
                'request' => $requestMock, 'routeConfig' => $routeConfigMock, ]
        );

        $baseUrl = 'http://localhost/index.php/';
        $urlType = \Magento\Framework\UrlInterface::URL_TYPE_LINK;

        $this->scopeMock->expects($this->once())->method('getBaseUrl')->will($this->returnValue($baseUrl));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->routeParamsResolverMock->expects($this->any())->method('getType')->will($this->returnValue($urlType));
        $this->routeParamsResolverMock->expects($this->any())->method('getRouteParams')
            ->will($this->returnValue(['id' => 100]));
        $requestMock->expects($this->once())->method('isDirectAccessFrontendName')->will($this->returnValue(true));
        $routeConfigMock->expects($this->once())->method('getRouteFrontName')->will($this->returnValue('catalog'));
        $this->queryParamsResolverMock->expects($this->once())->method('getQuery')
            ->will($this->returnValue($queryResult));

        $url = $model->getUrl('catalog/product/view', [
            '_fragment' => 'anchor',
            '_escape' => 1,
            '_query' => $query,
            '_nosid' => 0,
            'id' => 100
        ]);
        $this->assertEquals($returnUri, $url);
    }

    public function testGetUrlIdempotentSetRoutePath()
    {
        $model = $this->getUrlModel([
            'scopeResolver' => $this->scopeResolverMock,
            'routeParamsResolver' => $this->getRouteParamsResolver(),
        ]);
        $model->setData('route_path', 'catalog/product/view');

        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));

        $this->assertEquals('catalog/product/view', $model->getUrl('catalog/product/view'));
    }

    public function testGetUrlIdempotentSetRouteName()
    {
        $model = $this->getUrlModel([
            'scopeResolver' => $this->scopeResolverMock,
            'routeParamsResolver' => $this->getRouteParamsResolver(),
            'request' => $this->getRequestMock()
        ]);
        $model->setData('route_name', 'catalog');

        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));

        $this->assertEquals('/product/view/', $model->getUrl('catalog/product/view'));
    }

    public function testGetUrlRouteHasParams()
    {
        $this->routeParamsResolverMock->expects($this->any())->method('getRouteParams')
            ->will($this->returnValue(['foo' => 'bar', 'true' => false]));
        $model = $this->getUrlModel([
            'scopeResolver' => $this->scopeResolverMock,
            'routeParamsResolver' => $this->getRouteParamsResolver(),
            'request' => $this->getRequestMock()
        ]);

        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));

        $this->assertEquals('/index/index/foo/bar/', $model->getUrl('catalog'));
    }

    public function testGetUrlRouteUseRewrite()
    {
        $this->routeParamsResolverMock->expects($this->any())->method('getRouteParams')
            ->will($this->returnValue(['foo' => 'bar']));
        $request = $this->getRequestMock();
        $request->expects($this->once())->method('getAlias')->will($this->returnValue('/catalog/product/view/'));
        $model = $this->getUrlModel([
            'scopeResolver' => $this->scopeResolverMock,
            'routeParamsResolver' => $this->getRouteParamsResolver(),
            'request' => $request,
        ]);

        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));

        $this->assertEquals('/catalog/product/view/', $model->getUrl('catalog', ['_use_rewrite' => 1]));
    }

    public function getUrlDataProvider()
    {
        return [
            'string query' => [
                'foo=bar', 'foo=bar', 'http://localhost/index.php/catalog/product/view/id/100/?foo=bar#anchor',
            ],
            'array query' => [
                ['foo' => 'bar'], 'foo=bar', 'http://localhost/index.php/catalog/product/view/id/100/?foo=bar#anchor',
            ],
            'without query' => [false, '', 'http://localhost/index.php/catalog/product/view/id/100/#anchor'],
        ];
    }

    public function testGetUrlWithAsterisksPath()
    {
        $requestMock = $this->getRequestMock();
        $routeConfigMock = $this->getMock('Magento\Framework\App\Route\ConfigInterface');
        $model = $this->getUrlModel(
            ['scopeResolver' => $this->scopeResolverMock, 'routeParamsResolver' => $this->getRouteParamsResolver(),
                'queryParamsResolver' => $this->queryParamsResolverMock,
                'request' => $requestMock, 'routeConfig' => $routeConfigMock, ]
        );

        $baseUrl = 'http://localhost/index.php/';
        $urlType = \Magento\Framework\UrlInterface::URL_TYPE_LINK;

        $this->scopeMock->expects($this->once())->method('getBaseUrl')->will($this->returnValue($baseUrl));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->routeParamsResolverMock->expects($this->any())->method('getType')->will($this->returnValue($urlType));
        $this->routeParamsResolverMock->expects($this->any())->method('getRouteParams')
            ->will($this->returnValue(['key' => 'value']));
        $requestMock->expects($this->once())->method('isDirectAccessFrontendName')->will($this->returnValue(true));

        $requestMock->expects($this->once())->method('getRouteName')->will($this->returnValue('catalog'));
        $requestMock->expects($this->once())
            ->method('getControllerName')
            ->will($this->returnValue('product'));
        $requestMock->expects($this->once())->method('getActionName')->will($this->returnValue('view'));
        $routeConfigMock->expects($this->once())->method('getRouteFrontName')->will($this->returnValue('catalog'));

        $url = $model->getUrl('*/*/*/key/value');
        $this->assertEquals('http://localhost/index.php/catalog/product/view/key/value/', $url);
    }

    public function testGetDirectUrl()
    {
        $requestMock = $this->getRequestMock();
        $routeConfigMock = $this->getMock('Magento\Framework\App\Route\ConfigInterface');
        $model = $this->getUrlModel(
            ['scopeResolver' => $this->scopeResolverMock, 'routeParamsResolver' => $this->getRouteParamsResolver(),
                'queryParamsResolver' => $this->queryParamsResolverMock,
                'request' => $requestMock, 'routeConfig' => $routeConfigMock, ]
        );

        $baseUrl = 'http://localhost/index.php/';
        $urlType = \Magento\Framework\UrlInterface::URL_TYPE_LINK;

        $this->scopeMock->expects($this->once())->method('getBaseUrl')->will($this->returnValue($baseUrl));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->routeParamsResolverMock->expects($this->any())->method('getType')->will($this->returnValue($urlType));
        $requestMock->expects($this->once())->method('isDirectAccessFrontendName')->will($this->returnValue(true));

        $url = $model->getDirectUrl('direct-url');
        $this->assertEquals('http://localhost/index.php/direct-url', $url);
    }

    /**
     * @param string $url
     * @dataProvider getRebuiltUrlDataProvider
     */
    public function testGetRebuiltUrl($url)
    {
        $requestMock = $this->getRequestMock();
        $model = $this->getUrlModel([
            'session' => $this->sessionMock,
            'request' => $requestMock,
            'sidResolver' => $this->sidResolverMock,
            'scopeResolver' => $this->scopeResolverMock,
            'routeParamsResolver' => $this->getRouteParamsResolver(),
            'queryParamsResolver' => $this->queryParamsResolverMock,
        ]);

        $this->queryParamsResolverMock->expects($this->once())->method('getQuery')
            ->will($this->returnValue('query=123'));

        $this->assertEquals($url, $model->getRebuiltUrl($url));
    }

    public function testGetRedirectUrl()
    {
        $model = $this->getUrlModel(
            ['routeParamsResolver' => $this->getRouteParamsResolver(), 'session' => $this->sessionMock,
                'sidResolver' => $this->sidResolverMock, 'queryParamsResolver' => $this->queryParamsResolverMock, ]
        );

        $this->sidResolverMock->expects($this->once())->method('getUseSessionInUrl')->will($this->returnValue(true));
        $this->sessionMock->expects($this->once())->method('getSessionIdForHost')->will($this->returnValue(false));
        $this->sidResolverMock->expects($this->once())->method('getUseSessionVar')->will($this->returnValue(true));
        $this->routeParamsResolverMock->expects($this->once())->method('hasData')->with('secure_is_forced')
            ->will($this->returnValue(true));
        $this->sidResolverMock->expects($this->never())->method('getSessionIdQueryParam');
        $this->queryParamsResolverMock->expects($this->once())
            ->method('getQuery')
            ->will($this->returnValue('foo=bar'));

        $this->assertEquals('http://example.com/?foo=bar', $model->getRedirectUrl('http://example.com/'));
    }

    public function testGetRedirectUrlWithSessionId()
    {
        $model = $this->getUrlModel(
            ['routeParamsResolver' => $this->getRouteParamsResolver(), 'session' => $this->sessionMock,
                'sidResolver' => $this->sidResolverMock, 'queryParamsResolver' => $this->queryParamsResolverMock, ]
        );

        $this->sidResolverMock->expects($this->once())->method('getUseSessionInUrl')->will($this->returnValue(true));
        $this->sessionMock->expects($this->once())->method('getSessionIdForHost')
            ->will($this->returnValue('session-id'));
        $this->sidResolverMock->expects($this->once())->method('getUseSessionVar')->will($this->returnValue(false));
        $this->sidResolverMock->expects($this->once())->method('getSessionIdQueryParam');
        $this->queryParamsResolverMock->expects($this->once())
            ->method('getQuery')
            ->will($this->returnValue('foo=bar'));

        $this->assertEquals('http://example.com/?foo=bar', $model->getRedirectUrl('http://example.com/'));
    }

    public function getRebuiltUrlDataProvider()
    {
        return [
            'with port' => ['https://example.com:88/index.php/catalog/index/view?query=123#hash'],
            'without port' => ['https://example.com/index.php/catalog/index/view?query=123#hash'],
        ];
    }

    public function testGetRouteUrlWithValidUrl()
    {
        $model = $this->getUrlModel(['routeParamsResolver' => $this->getRouteParamsResolver()]);

        $this->routeParamsResolverMock->expects($this->never())->method('unsetData');
        $this->assertEquals('http://example.com', $model->getRouteUrl('http://example.com'));
    }

    public function testAddSessionParam()
    {
        $model = $this->getUrlModel([
            'session' => $this->sessionMock,
            'sidResolver' => $this->sidResolverMock,
            'queryParamsResolver' => $this->queryParamsResolverMock,
        ]);

        $this->sidResolverMock->expects($this->once())->method('getSessionIdQueryParam')->with($this->sessionMock)
            ->will($this->returnValue('sid'));
        $this->sessionMock->expects($this->once())->method('getSessionId')->will($this->returnValue('session-id'));
        $this->queryParamsResolverMock->expects($this->once())->method('setQueryParam')->with('sid', 'session-id');

        $model->addSessionParam();
    }

    /**
     * @param bool $result
     * @param string $baseUrl
     * @param string $referrer
     * @dataProvider isOwnOriginUrlDataProvider
     */
    public function testIsOwnOriginUrl($result, $baseUrl, $referrer)
    {
        $requestMock = $this->getRequestMock();
        $model = $this->getUrlModel(['scopeResolver' => $this->scopeResolverMock, 'request' => $requestMock]);

        $this->scopeMock->expects($this->any())->method('getBaseUrl')->will($this->returnValue($baseUrl));
        $this->scopeResolverMock->expects($this->any())->method('getScopes')
            ->will($this->returnValue([$this->scopeMock]));
        $requestMock->expects($this->once())->method('getServer')->with('HTTP_REFERER')
            ->will($this->returnValue($referrer));

        $this->assertEquals($result, $model->isOwnOriginUrl());
    }

    public function isOwnOriginUrlDataProvider()
    {
        return [
            'is origin url' => [true, 'http://localhost/', 'http://localhost/'],
            'is not origin url' => [false, 'http://localhost/', 'http://example.com/'],
        ];
    }

    /**
     * @param string $urlType
     * @param string $configPath
     * @param bool $isSecure
     * @param int $isSecureCallCount
     * @param string $key
     * @dataProvider getConfigDataDataProvider
     */
    public function testGetConfigData($urlType, $configPath, $isSecure, $isSecureCallCount, $key)
    {
        $urlSecurityInfoMock = $this->getMock('Magento\Framework\Url\SecurityInfoInterface');
        $model = $this->getUrlModel([
            'urlSecurityInfo' => $urlSecurityInfoMock,
            'routeParamsResolver' => $this->getRouteParamsResolver(),
            'scopeResolver' => $this->scopeResolverMock,
            'scopeConfig' => $this->scopeConfig,
        ]);

        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->with($this->equalTo($configPath), \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->scopeMock)
            ->will($this->returnValue('http://localhost/'));
        $this->routeParamsResolverMock->expects($this->at(0))->method('hasData')->with('secure_is_forced')
            ->will($this->returnValue(false));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->scopeMock->expects($this->once())->method('isUrlSecure')->will($this->returnValue(true));
        $this->routeParamsResolverMock->expects($this->at(1))->method('hasData')->with('secure')
            ->will($this->returnValue(false));
        $this->routeParamsResolverMock->expects($this->any())->method('getType')
            ->will($this->returnValue($urlType));
        $this->routeParamsResolverMock->expects($this->once())
            ->method('getData')
            ->will($this->returnValue($isSecure));
        $urlSecurityInfoMock->expects($this->exactly($isSecureCallCount))->method('isSecure')
            ->will($this->returnValue(false));

        $this->assertEquals('http://localhost/', $model->getConfigData($key));
    }

    public function getConfigDataDataProvider()
    {
        return [
            'secure url' => ['some-type', 'web/secure/base_url_secure', true, 0, 'base_url_secure'],
            'unsecure url' => [
                \Magento\Framework\UrlInterface::URL_TYPE_LINK,
                'web/unsecure/base_url_unsecure',
                false,
                1,
                'base_url_unsecure',
            ],
        ];
    }

    public function testGetConfigDataWithSecureIsForcedParam()
    {
        $model = $this->getUrlModel([
            'routeParamsResolver' => $this->getRouteParamsResolver(),
            'scopeResolver' => $this->scopeResolverMock,
            'scopeConfig' => $this->scopeConfig,
        ]);

        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->with(
                'web/secure/base_url_secure_forced',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->scopeMock
            )
            ->will($this->returnValue('http://localhost/'));
        $this->routeParamsResolverMock->expects($this->once())->method('hasData')->with('secure_is_forced')
            ->will($this->returnValue(true));
        $this->routeParamsResolverMock->expects($this->once())->method('getData')->with('secure')
            ->will($this->returnValue(true));

        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->assertEquals('http://localhost/', $model->getConfigData('base_url_secure_forced'));
    }

    /**
     * @param string $html
     * @param string $result
     * @dataProvider sessionUrlVarWithMatchedHostsAndBaseUrlDataProvider
     */
    public function testSessionUrlVarWithMatchedHostsAndBaseUrl($html, $result)
    {
        $requestMock = $this->getRequestMock();
        $model = $this->getUrlModel(
            ['session' => $this->sessionMock, 'request' => $requestMock, 'sidResolver' => $this->sidResolverMock,
                'scopeResolver' => $this->scopeResolverMock, 'routeParamsResolver' => $this->getRouteParamsResolver(), ]
        );

        $requestMock->expects($this->once())
            ->method('getHttpHost')
            ->will($this->returnValue('localhost'));
        $this->scopeMock->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('http://localhost'));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->sidResolverMock->expects($this->never())
            ->method('getSessionIdQueryParam');

        $this->assertEquals($result, $model->sessionUrlVar($html));
    }

    public function testSessionUrlVarWithoutMatchedHostsAndBaseUrl()
    {
        $requestMock = $this->getRequestMock();
        $model = $this->getUrlModel(
            ['session' => $this->sessionMock, 'request' => $requestMock, 'sidResolver' => $this->sidResolverMock,
                'scopeResolver' => $this->scopeResolverMock, 'routeParamsResolver' => $this->getRouteParamsResolver(), ]
        );

        $requestMock->expects($this->once())->method('getHttpHost')->will($this->returnValue('localhost'));
        $this->scopeMock->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('http://example.com'));
        $this->scopeResolverMock->expects($this->any())
            ->method('getScope')
            ->will($this->returnValue($this->scopeMock));
        $this->sidResolverMock->expects($this->once())->method('getSessionIdQueryParam')
            ->will($this->returnValue('SID'));
        $this->sessionMock->expects($this->once())->method('getSessionId')
            ->will($this->returnValue('session-id'));

        $this->assertEquals(
            '<a href="http://example.com/?SID=session-id">www.example.com</a>',
            $model->sessionUrlVar('<a href="http://example.com/?___SID=U">www.example.com</a>')
        );
    }

    public function sessionUrlVarWithMatchedHostsAndBaseUrlDataProvider()
    {
        return [
            [
                '<a href="http://example.com/?___SID=U?SID=session-id">www.example.com</a>',
                '<a href="http://example.com/?SID=session-id">www.example.com</a>',
            ],
            [
                '<a href="http://example.com/?___SID=U&SID=session-id">www.example.com</a>',
                '<a href="http://example.com/?SID=session-id">www.example.com</a>',
            ],
            [
                '<a href="http://example.com/?foo=bar&___SID=U?SID=session-id">www.example.com</a>',
                '<a href="http://example.com/?foo=bar?SID=session-id">www.example.com</a>',
            ],
            [
                '<a href="http://example.com/?foo=bar&___SID=U&SID=session-id">www.example.com</a>',
                '<a href="http://example.com/?foo=bar&SID=session-id">www.example.com</a>',
            ],
        ];
    }

    public function testSetRequest()
    {
        $initRequestMock = $this->getRequestMock();
        $requestMock = $this->getRequestMock();
        $initRequestMock->expects($this->any())->method('getScheme')->will($this->returnValue('fake'));
        $initRequestMock->expects($this->any())->method('getHttpHost')->will($this->returnValue('fake-host'));
        $requestMock->expects($this->any())->method('getScheme')->will($this->returnValue('http'));
        $requestMock->expects($this->any())->method('getHttpHost')->will($this->returnValue('example.com'));

        $model = $this->getUrlModel(['request' => $initRequestMock]);
        $model->setRequest($requestMock);
        $this->assertEquals('http://example.com', $model->getCurrentUrl());
    }
}
