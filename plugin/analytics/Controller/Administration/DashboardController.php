<?php

namespace Claroline\AnalyticsBundle\Controller\Administration;

use Claroline\AnalyticsBundle\Manager\AnalyticsManager;
use Claroline\AppBundle\API\FinderProvider;
use Claroline\AppBundle\API\SerializerProvider;
use Claroline\AppBundle\Controller\AbstractSecurityController;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Event\Log\LogGenericEvent;
use Claroline\CoreBundle\Manager\EventManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\Role;

/**
 * @EXT\Route("/tools/admin/analytics")
 */
class DashboardController extends AbstractSecurityController
{
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var AnalyticsManager */
    private $analyticsManager;

    /** @var EventManager */
    private $eventManager;

    /** @var User */
    private $loggedUser;

    /** @var SerializerProvider */
    private $serializer;

    /** @var FinderProvider */
    private $finder;

    /**
     * DashboardController constructor.
     *
     * @param TokenStorageInterface $tokenStorage
     * @param SerializerProvider    $serializer
     * @param FinderProvider        $finder
     * @param AnalyticsManager      $analyticsManager
     * @param EventManager          $eventManager
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        SerializerProvider $serializer,
        FinderProvider $finder,
        AnalyticsManager $analyticsManager,
        EventManager $eventManager
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->loggedUser = $tokenStorage->getToken()->getUser();
        $this->serializer = $serializer;
        $this->finder = $finder;
        $this->analyticsManager = $analyticsManager;
        $this->eventManager = $eventManager;
    }

    /**
     * @EXT\Route("/activity", name="apiv2_admin_tool_analytics_activity")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function activityAction(Request $request)
    {
        $this->canOpenAdminTool('dashboard');

        $query = $this->addOrganizationFilter($request->query->all());

        return new JsonResponse([
            'actions' => $this->analyticsManager->getDailyActions($query),
            'visitors' => $this->analyticsManager->getDailyActions(array_merge_recursive($query, [
                'hiddenFilters' => [
                    'action' => 'user-login',
                    'unique' => true,
                ],
            ])),
        ]);
    }

    /**
     * @EXT\Route("/actions", name="apiv2_admin_tool_analytics_actions")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function actionsAction(Request $request)
    {
        $this->canOpenAdminTool('dashboard');

        $query = $this->addOrganizationFilter($request->query->all());

        return new JsonResponse([
            'types' => $this->eventManager->getEventsForApiFilter(LogGenericEvent::DISPLAYED_ADMIN),
            'actions' => $this->analyticsManager->getDailyActions($query),
        ]);
    }

    /**
     * @EXT\Route("/time", name="apiv2_admin_tool_analytics_time")
     *
     * @return JsonResponse
     */
    public function connectionTimeAction()
    {
        $this->canOpenAdminTool('dashboard');

        return new JsonResponse([
            'total' => [],
            'average' => [],
        ]);
    }

    /**
     * @EXT\Route("/resources", name="apiv2_admin_tool_analytics_resources")
     *
     * @return JsonResponse
     */
    public function resourcesAction()
    {
        $this->canOpenAdminTool('dashboard');

        return new JsonResponse(
            $this->analyticsManager->getResourceTypesCount(null, $this->getLoggedUserOrganizations())
        );
    }

    /**
     * @EXT\Route("/resources/top", name="apiv2_admin_tool_analytics_top_resources")
     *
     * @return JsonResponse
     */
    public function topResourcesAction()
    {
        $this->canOpenAdminTool('dashboard');

        $options = [
            'page' => 0,
            'limit' => 10,
            'sortBy' => '-viewsCount',
            'hiddenFilters' => [
                'published' => true,
                'resourceTypeBlacklist' => ['directory'],
            ],
        ];

        $roles = array_map(function (Role $role) {
            return $role->getRole();
        }, $this->tokenStorage->getToken()->getRoles());

        if (!in_array('ROLE_ADMIN', $roles)) {
            $options['hiddenFilters']['roles'] = $roles;
        }

        return new JsonResponse(
            $this->finder->search(ResourceNode::class, $options)['data']
        );
    }

    /**
     * @EXT\Route("/users", name="apiv2_admin_tool_analytics_users")
     *
     * @return JsonResponse
     */
    public function usersAction()
    {
        $this->canOpenAdminTool('dashboard');

        return new JsonResponse(
            $this->analyticsManager->userRolesData(null, $this->getLoggedUserOrganizations())
        );
    }

    /**
     * @EXT\Route("/users/top", name="apiv2_admin_tool_analytics_top_users")
     *
     * @return JsonResponse
     */
    public function topUsersAction()
    {
        $this->canOpenAdminTool('dashboard');

        $options = [
            'page' => 0,
            'limit' => 10,
            'sortBy' => '-created',
            'hiddenFilters' => [
                'organization' => $this->loggedUser->getAdministratedOrganizations(),
            ],
        ];

        return new JsonResponse(
            $this->finder->search(User::class, $options)['data']
        );
    }

    private function addOrganizationFilter($query)
    {
        $this->canOpenAdminTool('dashboard');

        $organizations = $this->getLoggedUserOrganizations();
        if (null !== $organizations) {
            $query['hiddenFilters']['organization'] = $this->loggedUser->getAdministratedOrganizations();
        }

        return $query;
    }

    private function getLoggedUserOrganizations()
    {
        if (!$this->loggedUser->hasRole('ROLE_ADMIN')) {
            return $this->loggedUser->getAdministratedOrganizations();
        }

        return null;
    }
}