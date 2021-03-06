<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sIngress;
use RenokiCo\PhpK8s\ResourcesList;

class IngressTest extends TestCase
{
    /**
     * The default testing rules.
     *
     * @var array
     */
    protected static $rules = [
        ['host' => 'nginx.test.com', 'http' => [
            'paths' => [[
                'path' => '/',
                'backend' => [
                    'serviceName' => 'nginx',
                    'servicePort' => 80,
                ],
            ]],
        ]],
    ];

    public function setUp(): void
    {
        parent::setUp();

        // >= v1.18.0: https://kubernetes.io/blog/2020/04/02/improvements-to-the-ingress-api-in-kubernetes-1.18/
        if ($this->cluster->newerThan('1.18.0')) {
            self::$rules[0]['http']['paths'][0]['pathType'] = 'ImplementationSpecific';
        }
    }

    public function test_ingress_build()
    {
        $ing = K8s::ingress()
            ->setName('nginx')
            ->setAnnotations(['nginx/ann' => 'yes'])
            ->setRules(self::$rules);

        $this->assertEquals('networking.k8s.io/v1beta1', $ing->getApiVersion());
        $this->assertEquals('nginx', $ing->getName());
        $this->assertEquals(['nginx/ann' => 'yes'], $ing->getAnnotations());
        $this->assertEquals(self::$rules, $ing->getRules());
    }

    public function test_ingress_api_interaction()
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function runCreationTests()
    {
        $ing = K8s::ingress()
            ->onCluster($this->cluster)
            ->setName('nginx')
            ->setAnnotations(['nginx/ann' => 'yes'])
            ->setRules(self::$rules);

        $this->assertFalse($ing->isSynced());

        $ing = $ing->create();

        $this->assertTrue($ing->isSynced());

        $this->assertInstanceOf(K8sIngress::class, $ing);

        $this->assertEquals('networking.k8s.io/v1beta1', $ing->getApiVersion());
        $this->assertEquals('nginx', $ing->getName());
        $this->assertEquals(['nginx/ann' => 'yes'], $ing->getAnnotations());
        $this->assertEquals(self::$rules, $ing->getRules());
    }

    public function runGetAllTests()
    {
        $ingresss = K8s::ingress()
            ->onCluster($this->cluster)
            ->all();

        $this->assertInstanceOf(ResourcesList::class, $ingresss);

        foreach ($ingresss as $ing) {
            $this->assertInstanceOf(K8sIngress::class, $ing);

            $this->assertNotNull($ing->getName());
        }
    }

    public function runGetTests()
    {
        $ing = K8s::ingress()
            ->onCluster($this->cluster)
            ->whereName('nginx')
            ->get();

        $this->assertInstanceOf(K8sIngress::class, $ing);

        $this->assertTrue($ing->isSynced());

        $this->assertEquals('networking.k8s.io/v1beta1', $ing->getApiVersion());
        $this->assertEquals('nginx', $ing->getName());
        $this->assertEquals(['nginx/ann' => 'yes'], $ing->getAnnotations());
        $this->assertEquals(self::$rules, $ing->getRules());
    }

    public function runUpdateTests()
    {
        $ing = K8s::ingress()
            ->onCluster($this->cluster)
            ->whereName('nginx')
            ->get();

        $this->assertTrue($ing->isSynced());

        $ing->setAnnotations([]);

        $this->assertTrue($ing->update());

        $this->assertTrue($ing->isSynced());

        $this->assertEquals('networking.k8s.io/v1beta1', $ing->getApiVersion());
        $this->assertEquals('nginx', $ing->getName());
        $this->assertEquals([], $ing->getAnnotations());
        $this->assertEquals(self::$rules, $ing->getRules());
    }

    public function runDeletionTests()
    {
        $ingress = K8s::ingress()
            ->onCluster($this->cluster)
            ->whereName('nginx')
            ->get();

        $this->assertTrue($ingress->delete());

        $this->expectException(KubernetesAPIException::class);

        $ingress = K8s::ingress()
            ->onCluster($this->cluster)
            ->whereName('nginx')
            ->get();
    }

    public function runWatchAllTests()
    {
        $watch = K8s::ingress()
            ->onCluster($this->cluster)
            ->watchAll(function ($type, $ingress) {
                if ($ingress->getName() === 'nginx') {
                    return true;
                }
            }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests()
    {
        $watch = K8s::ingress()
            ->onCluster($this->cluster)
            ->whereName('nginx')
            ->watch(function ($type, $ingress) {
                return $ingress->getName() === 'nginx';
            }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
