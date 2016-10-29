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

namespace CampaignChain\Report\Analytics\MetricsPerLocationBundle\Resources\update\data;

use CampaignChain\UpdateBundle\Service\DataUpdateInterface;
use CampaignChain\Location\FacebookBundle\Job\ReportFacebookPageMetrics;
use CampaignChain\Location\TwitterBundle\Job\ReportTwitterUserMetrics;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateLocationReportScheduling implements DataUpdateInterface
{
    /**
     * @var Registry
     */
    private $em;

    /**
     * @var ReportFacebookPageMetrics
     */
    private $facebookPageMetrics;

    /**
     * @var ReportTwitterUserMetrics
     */
    private $twitterUserMetrics;

    /**
     * CreateLocationReportScheduling constructor.
     * @param ManagerRegistry $managerRegistry
     * @param ReportFacebookPageMetrics $facebookPageMetrics
     * @param ReportTwitterUserMetrics $twitterUserMetrics
     */
    public function __construct(ManagerRegistry $managerRegistry, ReportFacebookPageMetrics $facebookPageMetrics, ReportTwitterUserMetrics $twitterUserMetrics)
    {
        $this->em = $managerRegistry->getManager();
        $this->facebookPageMetrics = $facebookPageMetrics;
        $this->twitterUserMetrics = $twitterUserMetrics;
    }

    public function getVersion()
    {
        return 20160721124400;
    }

    public function getDescription()
    {
        return [
            'Search for already connected Locations',
            'If there are Location search for scheduled Location reports',
            'Add missing Locations reports',
        ];
    }

    public function execute(SymfonyStyle $io = null)
    {
        $existingLocations = $this->em
            ->getRepository('CampaignChainCoreBundle:Location')
            ->findAll();

        if (empty($existingLocations)) {
            $io->text('There is no Location to update');

            return true;
        }

        $supportedLocationModuleIdentifiers = [
            'campaignchain-twitter-user' => $this->twitterUserMetrics,
            'campaignchain-facebook-page' => $this->facebookPageMetrics,
        ];

        foreach ($existingLocations as $existingLocation) {
            if (!in_array($existingLocation->getLocationModule()->getIdentifier(), array_keys($supportedLocationModuleIdentifiers))) {
                continue;
            }

            //do we have already a scheduler?
            $existingScheduler = $this->em
                ->getRepository('CampaignChainCoreBundle:SchedulerReportLocation')
                ->findOneBy([
                    'location' => $existingLocation
                ]);

            if ($existingScheduler) {
                continue;
            }


            $service = $supportedLocationModuleIdentifiers[$existingLocation->getLocationModule()->getIdentifier()];
            $service->schedule($existingLocation);

        }

        $this->em->flush();

        return true;
    }

}