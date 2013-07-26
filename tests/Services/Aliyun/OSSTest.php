<?php
require_once str_replace(array('tests', 'Test.php'), array('src', '.php'), __FILE__);
class OSSTest extends PHPUnit_Framework_TestCase
{
    private $conf = array(
        'accessKeyId' => 'foo',
        'accessKeySecret' => 'bar',
    );

    public function testGetBuckets()
    {
        $c = new Services_Aliyun_OSS('', $this->conf);
        $r = $c->getBuckets();
        var_export($r);
        $this->assertEquals(true, is_array($r));
    }

    public function testPut()
    {
        $c = new Services_Aliyun_OSS('com-iqianggou-dev', $this->conf);
        $headers = array(
            'Content-Type' => 'image/jpeg',
        );
        $r = $c->put('/home/u1/2.jpg', '/2.jpg', $headers );
        var_dump($r);
        $this->assertArrayHasKey('internet', $r);
    }
    
    public function atestPutPublicWrite()
    {
        $c = new Services_Aliyun_OSS('com-example');
        $headers = array(
            'Content-Type' => 'image/jpeg',
        );
        $r = $c->putPublicWrite('/home/u1/2.jpg', '/2.jpg', $headers);
        var_dump($r);
        $this->assertEquals(true, $r);
    }
}
?>
