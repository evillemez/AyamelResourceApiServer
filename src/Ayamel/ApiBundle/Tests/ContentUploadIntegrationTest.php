<?php

namespace Ayamel\ApiBundle\Tests;
use Ayamel\ApiBundle\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ContentUploadIntegrationTest extends TestCase
{
    
    //a series of test as this is a one-time-use url
    public function testGetUploadUrl()
    {
        $data = array(
            'title' => 'test'
        );
        
        $response = $this->getJson('POST', '/api/v1/resources', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertFalse(isset($response['resource']['content']));
        
        $resourceId = $response['resource']['id'];
        $apiPath = substr($response['content_upload_url'], strlen('http://localhost'));
        
        //hit the path with empty request, expect 422 (unprocessable) - then 401 on subsequent requests
        $response = $this->getResponse('POST', $apiPath);
        $this->assertSame(422, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame(422, $content['response']['code']);
        
        $response = $this->getResponse('POST', $apiPath);
        $this->assertSame(401, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame(401, $content['response']['code']);
        
        //now get a new one-time url
        $response = $this->getResponse('GET', '/api/v1/resources/'.$resourceId."/request-upload-url");
        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame(200, $content['response']['code']);
        $this->assertTrue(isset($content['content_upload_url']));
        $uploadUrl = substr($content['content_upload_url'], strlen('http://localhost'));
        
        $response = $this->getResponse('POST', $uploadUrl);
        $this->assertSame(422, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame(422, $content['response']['code']);
        
        $response = $this->getResponse('POST', $uploadUrl);
        $this->assertSame(401, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame(401, $content['response']['code']);        
    }

    public function testUploadContentAsRemoteFilesArray()
    {
        $data = array(
            'title' => 'test'
        );
        
        $response = $this->getJson('POST', '/api/v1/resources', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertFalse(isset($response['resource']['content']));
        $this->assertSame('awaiting_content', $response['resource']['status']);
        
        $resourceId = $response['resource']['id'];
        $apiPath = substr($response['content_upload_url'], strlen('http://localhost'));
        
        //TODO: make sure these are validated properly w/ object validator
        //TODO: there really needs to be validation on the attribute fields as well
        $data = array(
            'remoteFiles' => array(
                array(
                    'downloadUri' => 'http://example.com/files/test.mp4',
                    'streamUri' => 'http://streaming.example.com/test',
                    'representation' => 'original',
                    'quality' => 1,
                    'mime' => 'video/mp4',
                    'mimeType' => 'video/mp4; encoding=binary',
                    'attributes' => array(
                        'key' => 'val',
                        'foo' => 'bar'
                    )
                ),
                array(
                    'downloadUri' => 'http://example.com/files/test.low.mp4',
                    'streamUri' => 'http://streaming.example.com/test.low',
                    'representation' => 'transcoding',
                    'quality' => 0,
                    'mime' => 'video/mp4',
                    'mimeType' => 'video/mp4; encoding=binary',
                    'attributes' => array(
                        'key' => 'val',
                        'foo' => 'bar'
                    )
                )
            )
        );
        $response = $this->getJson('POST', $apiPath, array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        
        $this->assertSame(200, $response['response']['code']);
        $this->assertSame($data['remoteFiles'], $response['resource']['content']['files']);
        $this->assertSame('normal', $response['resource']['status']);
    }
    
    public function testUploadContentAsFile()
    {
        //get content upload url
        $data = array(
            'title' => 'test'
        );
        
        $response = $this->getJson('POST', '/api/v1/resources', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertFalse(isset($response['resource']['content']));
        $resourceId = $response['resource']['id'];
        $uploadUrl = substr($response['content_upload_url'], strlen('http://localhost'));
        
        //create uploaded file
        $testFilePath = __DIR__."/files/resource_test_files/lorem.txt";
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'lorem.txt',
            'text/plain',
            filesize($testFilePath)
        );
        
        $content = $this->getJson('POST', $uploadUrl, array(), array('file' => $uploadedFile));
        
        $this->assertSame(202, $content['response']['code']);
        $this->assertSame('awaiting_processing', $content['resource']['status']);
        $this->assertSame($data['title'], $content['resource']['title']);
        $this->assertTrue(isset($content['resource']['content']));
        $this->assertTrue(isset($content['resource']['content']['files']));
        $data = $content['resource']['content']['files'][0];
        $this->assertTrue(isset($data['downloadUri']));
        $this->assertSame('text/plain', $data['mime']);
        $this->assertSame('text/plain', $data['mimeType']);
        $this->assertSame(filesize($testFilePath), $data['attributes']['bytes']);
    }
    
    public function testUploadAndTranscodeFile()
    {
        $data = array(
            'title' => 'test'
        );
        
        $response = $this->getJson('POST', '/api/v1/resources', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertFalse(isset($response['resource']['content']));
        $this->assertSame('awaiting_content', $response['resource']['status']);
        $resourceId = $response['resource']['id'];
        $uploadUrl = substr($response['content_upload_url'], strlen('http://localhost'));
        
        //create uploaded file
        $testFilePath = __DIR__."/files/resource_test_files/lorem.txt";
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'lorem.txt',
            'text/plain',
            filesize($testFilePath)
        );
        
        $content = $this->getJson('POST', $uploadUrl, array(), array('file' => $uploadedFile));
        
        $this->assertSame(202, $content['response']['code']);
        $this->assertSame('awaiting_processing', $content['resource']['status']);
        $this->assertSame($data['title'], $content['resource']['title']);
        $this->assertTrue(isset($content['resource']['content']));
        $this->assertTrue(isset($content['resource']['content']['files']));
        $this->assertSame(1, count($content['resource']['content']['files']));
        $data = $content['resource']['content']['files'][0];
        $this->assertTrue(isset($data['downloadUri']));
        $this->assertSame('text/plain', $data['mime']);
        $this->assertSame('text/plain', $data['mimeType']);
        $this->assertSame(filesize($testFilePath), $data['attributes']['bytes']);
        
        //now run transcode command directly
        $this->runCommand(sprintf('api:resource:transcode %s --force', $resourceId));
        
        //now get resource - expect 2 files and changed status
        $json = $this->getJson('GET', '/api/v1/resources/'.$resourceId, array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ));
        
        $expected = array(
            'mime' => 'text/plain',
            'mimeType' => 'text/plain',
            'representation' => 'transcoding',
            'quality' => 0,
            'attributes' => array(
                'bytes' => filesize($testFilePath)
            )
        );

        $this->assertSame(200, $json['response']['code']);
        $this->assertSame('normal', $json['resource']['status']);
        $this->assertSame(2, count($json['resource']['content']['files']));
        $transcoded = $json['resource']['content']['files'][1];
        $this->assertSame($expected['mime'], $transcoded['mime']);
        $this->assertSame($expected['mimeType'], $transcoded['mimeType']);
        $this->assertSame($expected['representation'], $transcoded['representation']);
        $this->assertSame($expected['quality'], $transcoded['quality']);
        $this->assertSame($expected['attributes']['bytes'], $transcoded['attributes']['bytes']);
        $this->assertTrue(isset($transcoded['downloadUri']));
        
        //hit one-time url again to make sure it expired
        $response = $this->getResponse('POST', $uploadUrl, array(), array('file' => $uploadedFile));
        $this->assertSame(401, $response->getStatusCode());
    }
    
}
