<?php

namespace Oro\Bridge\CustomerAccount\Tests\Unit\Helper;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Oro\Bridge\CustomerAccount\Helper\CustomerAccountImportExportHelper;
use Oro\Bundle\AccountBundle\Entity\Account;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SalesBundle\Entity\Customer as SalesCustomer;
use Oro\Bundle\SalesBundle\Entity\Manager\AccountCustomerManager;
use Oro\Bundle\SalesBundle\Entity\Repository\CustomerRepository as SalesCustomerRepository;
use Oro\Bundle\SalesBundle\Tests\Unit\Fixture\CustomerStub as SalesCustomerStub;

use Oro\Component\Testing\Unit\EntityTrait;

class CustomerAccountImportExportHelperTest extends \PHPUnit_Framework_TestCase
{
    use EntityTrait;

    /**
     * @var CustomerAccountImportExportHelper
     */
    private $customerAccountImportExportHelper;

    /**
     * @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $doctrineHelper;

    /**
     * @var SalesCustomerRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    private $salesCustomerRepository;

    /**
     * @var EntityRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    private $accountRepository;

    /**
     * @var EntityManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $entityManager;


    /**
     * @var Customer[]
     */
    private $customers = [];

    /**
     * @var SalesCustomer[]
     */
    private $salesCustomers = [];

    /**
     * @var Account[]
     */
    private $accounts = [];

    protected function setUp()
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->salesCustomerRepository = $this->createMock(SalesCustomerRepository::class);
        $this->accountRepository = $this->createMock(EntityRepository::class);
        $this->entityManager = $this->createMock(EntityManager::class);

        $this->customerAccountImportExportHelper = new CustomerAccountImportExportHelper(
            $this->doctrineHelper,
            SalesCustomerStub::class
        );

        $this->createCustomers();
        $this->createSalesCustomer();
    }

    /**
     * @dataProvider loadCustomerAccountsDataProvider
     * @param Customer[] $customers
     * @param Account[] $expectedResult
     */
    public function testLoadCustomerAccounts(array $customers, array $expectedResult)
    {
        $this->shouldCallSalesCustomerRepository(count($customers));
        $this->andShouldGetCustomerByTarget(count($customers));

        $result = $this->customerAccountImportExportHelper->loadCustomerAccounts($customers);

        $this->assertSame(count($expectedResult), count($result));
        foreach ($expectedResult as $id => $account) {
            $this->assertArrayHasKey($id, $result);
            $this->assertEquals($account, $result[$id]);
        }
    }

    /**
     * @dataProvider denormalizeAccountWithProperArrayDataProvider
     * @param array $data
     * @param Account|null $expectedAccount
     */
    public function testDenormalizeAccountWithProperArray(array $data, Account $expectedAccount = null)
    {
        $this->shouldCallAccountRepository(1);
        $this->andShouldFind(1);

        $account = $this->customerAccountImportExportHelper->fetchAccount($data['account']['id']);
        $this->assertEquals($expectedAccount, $account);
    }

    public function testAssignAccountNoNeedToUpdate()
    {
        $this->shouldCallSalesCustomerRepository(1);
        $this->andShouldGetCustomerByTarget(1);

        $this->customerAccountImportExportHelper->assignAccount($this->customers[1], $this->accounts[1]);

        $this->assertEquals(
            $this->getEntity(
                SalesCustomerStub::class,
                ['id' => 1, 'account' => $this->accounts[1], 'customerTarget' => $this->customers[1]]
            ),
            $this->salesCustomers[1]
        );
    }

    public function testAssignAccountShouldUpdateAccountForCustomer()
    {
        $this->shouldCallSalesCustomerRepository(1);
        $this->andShouldGetCustomerByTarget(1);

        $this->customerAccountImportExportHelper->assignAccount($this->customers[1], $this->accounts[2]);

        $this->assertEquals(
            $this->getEntity(
                SalesCustomerStub::class,
                ['id' => 1, 'account' => $this->accounts[2], 'customerTarget' => $this->customers[1]]
            ),
            $this->salesCustomers[1]
        );
    }

    public function testAssignAccountShouldAddExistingAccountToNewCustomer()
    {
        $this->shouldCallEntityManager(1);
        $this->andShouldPersist(1);

        /** @var Customer $customer */
        $customer = $this->getEntity(Customer::class, ['id' => null]);
        $this->customerAccountImportExportHelper->assignAccount($customer, $this->accounts[1]);
    }

    /**
     * @return array
     */
    public function loadCustomerAccountsDataProvider()
    {
        $this->createCustomers();
        $this->createAccounts();

        return [
            'one_result' => [
                [$this->customers[1]],
                [
                    1 => $this->accounts[1]
                ],
            ],
            'two_results' => [
                [$this->customers[1], $this->customers[2]],
                [
                    1 => $this->accounts[1],
                    2 => $this->accounts[2],
                ],
            ],
            'missing_results' => [
                [$this->customers[1], $this->customers[11]],
                [
                    1 => $this->accounts[1],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function denormalizeAccountWithProperArrayDataProvider()
    {
        $this->createAccounts();

        return [
            'account_exists' => [
                ['account' => ['id' => 1]],
                $this->accounts[1]
            ],
            'account_does_not_exists' => [
                ['account' => ['id' => 10000]],
                null
            ],
        ];
    }

    /**
     * @param int $howManyTimes
     */
    private function shouldCallSalesCustomerRepository($howManyTimes)
    {
        $this->doctrineHelper
            ->expects($this->exactly($howManyTimes))
            ->method('getEntityRepository')
            ->with(SalesCustomer::class)
            ->willReturn($this->salesCustomerRepository);
    }

    private function shouldCallAccountRepository($howManyTimes)
    {
        $this->doctrineHelper
            ->expects($this->exactly($howManyTimes))
            ->method('getEntityRepository')
            ->with(Account::class)
            ->willReturn($this->accountRepository);
    }

    /**
     * @param int $howManyTimes
     */
    private function shouldCallEntityManager($howManyTimes)
    {
        $this->doctrineHelper
            ->expects($this->exactly($howManyTimes))
            ->method('getEntityManagerForClass')
            ->willReturn($this->entityManager);
    }

    private function andShouldPersist($howManyTimes)
    {
        $this->entityManager
            ->expects($this->exactly($howManyTimes))
            ->method('persist');
    }

    /**
     * @param int $howManyTimes
     */
    private function andShouldGetCustomerByTarget($howManyTimes)
    {
        $map = [];

        foreach ($this->customers as $customer) {
            $map[] = [
                $customer->getId(),
                $this->getCustomerTargetField(),
                isset($this->salesCustomers[$customer->getId()]) ? $this->salesCustomers[$customer->getId()] : null,
            ];
        }

        $this->salesCustomerRepository
            ->expects($this->exactly($howManyTimes))
            ->method('getCustomerByTarget')
            ->willReturnMap($map);
    }

    /**
     * @param $howManyTimes
     */
    private function andShouldFind($howManyTimes)
    {
        $map = [];

        foreach ($this->accounts as $i => $account) {
            $map[] = [$account->getId(), null, null, $account];
        }

        $this->accountRepository
            ->expects($this->exactly($howManyTimes))
            ->method('find')
            ->willReturnMap($map);
    }

    /**
     * We have more Customers than SalesCustomers, to check negative scenarios
     * when Customer doesn't have SalesCustomer.
     */
    private function createCustomers()
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->customers[$i] = $this->getEntity(Customer::class, ['id' => $i]);
        }
    }

    private function createAccounts()
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->accounts[$i] = $this->getEntity(
                Account::class,
                [
                    'id' => $i,
                    'name' => "Account {$i}"
                ]
            );
        }
    }

    private function createSalesCustomer()
    {
        if (empty($this->accounts)) {
            $this->createAccounts();
        }

        if (empty($this->customers)) {
            $this->createCustomers();
        }

        for ($i = 1; $i <= 10; $i++) {
            $this->salesCustomers[$i] = $this->getEntity(
                SalesCustomerStub::class,
                [
                    'id' => $i,
                    'account' => $this->accounts[$i],
                    'customerTarget' => $this->customers[$i],
                ]
            );
        }
    }

    /**
     * @return string
     */
    private function getCustomerTargetField()
    {
        return AccountCustomerManager::getCustomerTargetField(Customer::class);
    }
}
