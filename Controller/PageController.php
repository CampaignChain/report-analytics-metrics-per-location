<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Report\Analytics\MetricsPerLocationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use CampaignChain\CoreBundle\Entity\Campaign;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityRepository;

class PageController extends Controller
{
    public function indexAction(Request $request){
        $campaign = array();
        $form = $this->createFormBuilder($campaign)
            ->setMethod('GET')
            ->add('campaign', 'entity', array(
                'label' => 'Campaign',
                'class' => 'CampaignChainCoreBundle:Campaign',
                // Only display campaigns for selection that actually have report data
                'query_builder' => function(EntityRepository $er) {
                        return $er->createQueryBuilder('campaign')
                            ->join('campaign.locationFacts', 'r')
                            ->groupBy('campaign.id')
                            ->orderBy('campaign.startDate', 'ASC');
                    },
                'property' => 'name',
                'empty_value' => 'Select a Campaign',
                'empty_data' => null,
            ))
            ->getForm();

        $form->handleRequest($request);

        $tplVars = array(
            'page_title' => 'Metrics Per Location',
            'form' => $form->createView(),
        );

        if ($form->isValid()) {
            $campaign = $form->getData()['campaign'];
            $dataService = $this->get('campaignchain.report.analytics.metrics_per_location.data');
            $tplVars['report_data'] = $dataService->getCampaignSeries($campaign);
            $tplVars['campaign_data'] = $dataService->getCampaignData($campaign);
            $tplVars['milestone_data'] = $dataService->getMilestonesData($campaign);
            $tplVars['markings_data'] = $dataService->getMilestonesMarkings($campaign);
        }

        return $this->render(
            'CampaignChainReportAnalyticsMetricsPerLocationBundle:Page:index.html.twig',
            $tplVars);
    }

//    public function locationAction(Request $request, $id){
//        // TODO: If an activity is done, it cannot be edited.
//        $location = $this->getDoctrine()
//            ->getRepository('CampaignChainCoreBundle:Location')
//            ->find($id);
//
//        if (!$location) {
//            throw new \Exception(
//                'No location found for id '.$id
//            );
//        }
//
//        $campaign = $location->getCampaign();
//
//        $dataService = $this->get('campaignchain.report.analytics.metrics_per_activity.data');
//
//        return $this->render(
//            'CampaignChainReportAnalyticsMetricsPerActivityBundle:Page:activity.html.twig',
//            array(
//                'page_title' => 'Metrics Per Activity',
//                'report_data' => $dataService->getActivitySeries($activity),
//                'campaign_data' => $dataService->getCampaignData($campaign),
//                'milestone_data' => $dataService->getMilestonesData($campaign),
//                'markings_data' => $dataService->getMilestonesMarkings($campaign),
//            ));
//    }
}