<?php

namespace Oro\Bridge\CustomerAccount\Tests\Functional\EventListener;

use Doctrine\ORM\EntityManager;

use Oro\Bundle\SalesBundle\Entity\Manager\AccountCustomerManager;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\CustomerBundle\Entity\Customer as Customer;
use Oro\Bundle\SalesBundle\Entity\Customer as CustomerAssociation;
use Oro\Bundle\AccountBundle\Entity\Account;

/**
 * @outputBuffering enabled
 * @dbIsolation
 */
class CustomerCreateListenerTest extends WebTestCase
{
    protected function setUp()
    {
        $this->initClient();
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     */
    public function testCreateCustomerWithoutAccount()
    {
        $em = $this->getEntityManager();
        $customer = new Customer();
        $customer->setName('Test Customer');

        $em->persist($customer);
        $em->flush();

        /** @var AccountCustomerManager $accountCustomerManager */
        $accountCustomerManager = $this->getContainer()->get('oro_sales.manager.account_customer');
        $customerAssociation = $accountCustomerManager->getAccountCustomerByTarget($customer);
        $account = $customerAssociation->getAccount();

        $this->assertEquals('Test Customer', $account->getName());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     */
    public function testCreateCustomerWithAccount()
    {
        $em = $this->getEntityManager();
        $customer = new Customer();
        $customer->setName('Test Customer');
        $account = new Account();
        $account->setName('Test Account');

        $customerAssociation = new CustomerAssociation();
        $customerAssociation->setTarget($account, $customer);

        $em->persist($account);
        $em->persist($customer);
        $em->persist($customerAssociation);
        $em->flush();

        /** @var AccountCustomerManager $accountCustomerManager */
        $accountCustomerManager = $this->getContainer()->get('oro_sales.manager.account_customer');
        $customerAssociation = $accountCustomerManager->getAccountCustomerByTarget($customer);
        $account = $customerAssociation->getAccount();

        $this->assertEquals('Test Account', $account->getName());
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }
}
