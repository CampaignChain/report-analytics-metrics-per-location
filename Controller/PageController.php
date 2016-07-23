<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
