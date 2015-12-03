<?php

namespace Oro\Bundle\CronBundle\Controller;

use JMS\JobQueueBundle\Entity\Job;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/job")
 */
class JobController extends Controller
{
    /**
     * @Route(
     *      "/{page}/{limit}",
     *      name="oro_cron_job_index",
     *      requirements={"page"="\d+", "limit"="\d+"},
     *      defaults={"page"=1, "limit"=20}
     * )
     * @Template
     * @AclAncestor("oro_jobs")
     */
    public function indexAction($page, $limit)
    {
        return array(
            'pid'   => $this->get('oro_cron.job_daemon')->getPid(),
            'pager' => $this->get('knp_paginator')->paginate(
                $this->get('oro_cron.job_manager')->getListQuery(),
                (int) $page,
                (int) $limit
            ),
        );
    }

    /**
     * @Route("/view/{id}", name="oro_cron_job_view", requirements={"id"="\d+"})
     * @Template
     * @AclAncestor("oro_jobs")
     */
    public function viewAction(Job $job)
    {
        $manager    = $this->get('oro_cron.job_manager');
        $statisticsEnabled = $this->container->hasParameter('jms_job_queue.statistics')
            && $this->container->getParameter('jms_job_queue.statistics');
        $statistics = $statisticsEnabled
            ? $manager->getJobStatistics($job)
            : array();

        return array(
            'entity'          => $job,
            'pid'             => $this->get('oro_cron.job_daemon')->getPid(),
            'relatedEntities' => $manager->getRelatedEntities($job),
            'statistics'      => $statistics,
            'dependencies'    => $this->getDoctrine()
                ->getRepository('JMSJobQueueBundle:Job')
                ->getIncomingDependencies($job),
        );
    }

    /**
     * @Route("/run-daemon", name="oro_cron_job_run_daemon")
     * @AclAncestor("oro_jobs")
     */
    public function runDaemonAction(Request $request)
    {
        $daemon     = $this->get('oro_cron.job_daemon');
        $translator = $this->get('translator');
        $ret        = array('error' => 1);

        try {
            if ($pid = $daemon->run()) {
                $ret['error']   = 0;
                $ret['message'] = $pid;
            } else {
                $ret['message'] = $translator->trans('oro.cron.message.start.fail');
            }
        } catch (\RuntimeException $e) {
            $ret['message'] = $e->getMessage();
        }

        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode($ret));
        } else {
            if ($ret['error']) {
                $this->get('session')->getFlashBag()->add('error', $ret['message']);
            } else {
                $this->get('session')->getFlashBag()->add(
                    'success',
                    $translator->trans('oro.cron.message.start.success')
                );
            }

            return $this->redirect($this->generateUrl('oro_cron_job_index'));
        }
    }

    /**
     * @Route("/stop-daemon", name="oro_cron_job_stop_daemon")
     * @AclAncestor("oro_jobs")
     */
    public function stopDaemonAction(Request $request)
    {
        $daemon     = $this->get('oro_cron.job_daemon');
        $translator = $this->get('translator');
        $ret        = array('error' => 1);

        try {
            if ($daemon->stop()) {
                $ret['error']   = 0;
                $ret['message'] = $translator->trans('oro.cron.message.stop.success');
            } else {
                $ret['message'] = $translator->trans('oro.cron.message.stop.fail');
            }
        } catch (\RuntimeException $e) {
            $ret['message'] = $e->getMessage();
        }

        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode($ret));
        } else {
            $this->get('session')->getFlashBag()->add($ret['error'] ? 'error' : 'success', $ret['message']);

            return $this->redirect($this->generateUrl('oro_cron_job_index'));
        }
    }

    /**
     * @Route("/status", name="oro_cron_job_status")
     * @AclAncestor("oro_jobs")
     */
    public function statusAction()
    {
        return $this->getRequest()->isXmlHttpRequest()
            ? new Response($this->get('oro_cron.job_daemon')->getPid())
            : $this->redirect($this->generateUrl('oro_cron_job_index'));
    }
}
