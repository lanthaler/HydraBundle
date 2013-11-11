
    public function testCompleteScenario()
    {
        // Create a new client to browse the application
        $client = static::createClient();

        // Create a new entry in the database
        $client->request('GET', '/{{ route_prefix }}/');
        $this->assertTrue(200 === $client->getResponse()->getStatusCode());
        $request = $client->getRequestTemplate($client->selectOperation('CreateOperation'));

        // Create entity to submit
        $data = new {{ entity_class }}();
        // ... fill fields
        $request->setData($data);

        $client->submit($request);
        $client->followRedirect();

        // Check data in the show view
        // $this->assertTrue();

        // Edit the entity
        $client->request('GET', $client->getId());

        $request = $client->getRequestTemplate($client->selectOperation('ReplaceOperation'));

        // Update the entity
        $data = $request->getData();
        // ... fill fields
        $request->setData($data);

        $client->submit($request);

        // Check that the entity was changed
        // $this->assertTrue($crawler->filter('[value="Foo"]')->count() > 0);

        // Delete the entity
        $id = $client->getId();
        $client->request('DELETE', $id);

        $this->assertTrue(200 === $client->getResponse()->getStatusCode());

        $client->request('GET', $id);
        $this->assertTrue(404 === $client->getResponse()->getStatusCode());

        // Check the entity has been deleted from the collection
        //$this->assertNotRegExp("/$id/", $client->getResponse()->getContent());
    }
