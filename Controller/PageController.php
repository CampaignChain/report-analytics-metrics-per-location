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

use CampaignChain\CoreBundle\Entity\Campaign;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PageController extends Controller
{
    public function indexAction(Request $request)
    {
        $campaign = [];
        $form = $this->createFormBuilder($campaign)
            ->setMethod('GET')
            ->add(
                'campaign',
                EntityType::class,
                [
                    'label' => 'Campaign',
                    'class' => 'CampaignChainCoreBundle:Campaign',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('campaign')
                            ->groupBy('campaign.id')
                            ->orderBy('campaign.startDate', 'ASC');
                    },
                    'property' => 'name',
                    'empty_value' => 'Select a Campaign',
                    'empty_data' => null,
                ]
            )
            ->getForm();

        $form->handleRequest($request);

        $tplVars = [
            'page_title' => 'Metrics Per Location',
            'form' => $form->createView(),
        ];

        if ($form->isValid()) {
            $campaign = $form->getData()['campaign'];
            $dataService = $this->get('campaignchain.report.analytics.metrics_per_location.data');
            $tplVars['report_data'] = $dataService->getCampaignSeries();
            $tplVars['campaign_data'] = $dataService->getCampaignData($campaign);
            $tplVars['milestone_data'] = $dataService->getMilestonesData($campaign);
            $tplVars['markings_data'] = $dataService->getMilestonesMarkings($campaign);
        }

        return $this->render(
            'CampaignChainReportAnalyticsMetricsPerLocationBundle:Page:index.html.twig',
            $tplVars
        );
    }
}
