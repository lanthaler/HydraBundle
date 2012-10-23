
    public function testCompleteScenario()
    {
        // Create a new client to browse the application
        $client = static::createClient();

        // Retrieve the collection
        $crawler = $client->request('GET', '/{{ route_prefix }}/');
        $this->assertTrue(200 === $client->getResponse()->getStatusCode());

        // Retrieve the first member
        $crawler = $client->click($crawler->selectLink('show')->link());
        $this->assertTrue(200 === $client->getResponse()->getStatusCode());
    }
