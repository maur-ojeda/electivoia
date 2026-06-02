<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ChatbotControllerIntegrationTest extends WebTestCase
{
    public function testUnauthenticatedRequestReturns401(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/chatbot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['message' => 'Hola']));
        
        $this->assertEquals(401, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('iniciar sesión', $data['message']);
    }

    public function testEmptyMessageReturns400(): void
    {
        $client = static::createClient();
        
        // Login as student
        $this->loginAsStudent($client);
        
        $client->request('POST', '/api/chatbot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['message' => '']));
        
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testValidMessageReturnsSuccess(): void
    {
        $client = static::createClient();
        
        // Login as student
        $this->loginAsStudent($client);
        
        $client->request('POST', '/api/chatbot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['message' => 'Hola']));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['message']);
    }

    public function testClearHistoryEndpoint(): void
    {
        $client = static::createClient();
        
        // Login as student
        $this->loginAsStudent($client);
        
        $client->request('POST', '/api/chatbot/clear');
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    private function loginAsStudent($client): void
    {
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        
        // Find or create a test student user
        $userRepository = $entityManager->getRepository(\App\Entity\User::class);
        $user = $userRepository->findOneBy(['email' => 'test-student@example.com']);
        
        if (!$user) {
            $user = new \App\Entity\User();
            $user->setEmail('test-student@example.com');
            $user->setPassword('test-password');
            $user->setRoles(['ROLE_STUDENT']);
            $entityManager->persist($user);
            $entityManager->flush();
        }
        
        $client->loginUser($user, 'main');
    }
}
