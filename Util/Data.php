<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Report\Analytics\MetricsPerLocationBundle\Util;

use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Entity\Campaign;
use CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact;
use CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityMetric;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Serializer\SerializerInterface;

class Data
{
    const ONE_SERIES_PER_DIMENSION = 'one_series_per_dimension';

    const ALL_DIMENSIONS_PER_ACTIVITY = 'all_dimensions_per_activity';

    protected $em;

    private $serializer;

    public $campaign;

    public $milestones;

    public $dimensions;

    public $campaignDuration;

    public $campaignData;

    public $milestonesMarkings = null;

    public $milestonesData = null;

    public function __construct(EntityManager $em, SerializerInterface $serializer)
    {
        $this->em = $em;

        // We'll need the serializer later
        $this->serializer = $serializer;
    }

    public function setCampaign($campaign){
        $this->campaign = $campaign;
    }

    public function getCampaignData($campaign){
        $this->campaignData['duration'] = $this->getCampaignDuration($campaign);
        // TODO: Formatting should be handled by Datetime service.
        $this->campaignData['startDate'] = $campaign->getStartDate()->format('F d, Y H:i:s');
        $this->campaignData['endDate'] = $campaign->getEndDate()->format('F d, Y H:i:s');

        return $this->campaignData;
    }

    /**
     * @param $campaign
     * @return array
     */
    public function getCampaignSeries($campaign){
        $activities = $this->getActivities($campaign);
        $seriesData = [];
        $metricIds = [];

        foreach($activities as $activity){
            $factsData = $this->getFacts($campaign, $activity->getActivity());
            $seriesData[] = [
                'activity' => $activity->getActivity(),
                'dimensions' => $factsData,
            ];

            $usedMetricIds = array_keys($factsData);
            foreach ($usedMetricIds as $metricId) {
                if (in_array($metricId, $metricIds)) {
                    continue;
                }
                $metricIds[] = $metricId;
            }
        }

        return [
            'data' => $seriesData,
            'metricNames' => $this->getMetricNamesById($metricIds),
        ];
    }

    public function getActivitySeries($activity, $structure = self::ONE_SERIES_PER_DIMENSION){
        $factsData = $this->getFacts($activity->getCampaign(), $activity, true);
        $metricNames = $this->getMetricNamesById(array_keys($factsData));

        $finalFactsdata = [];
        foreach ($factsData as $k => $d) {
            $finalFactsdata[$metricNames[$k]] = $d;
        }

        $seriesData[] = array(
            'activity' => $activity,
            'dimensions' => $finalFactsdata,
        );

        return $seriesData;
    }

    public function getMilestonesData($campaign){
        $milestones = $this->getMilestones($campaign);

        foreach($milestones as $milestone){
            $this->milestonesData .= '{';
            $this->milestonesData .= '    x:'.$milestone->getJavascriptTimestamp().',';
            $this->milestonesData .= '    y: 0,';
            $this->milestonesData .= '    contents: "'.$milestone->getName()/*.'<br/>'.$milestone->getDue()->format('Y-m-d H:i')*/.'"';
            $this->milestonesData .= '},';
        }

        return rtrim($this->milestonesData, ',');
    }

    public function getMilestonesMarkings($campaign){
        $milestones = $this->getMilestones($campaign);

        foreach($milestones as $milestone){
            $this->milestonesMarkings .= '{';
            $this->milestonesMarkings .= 'xaxis: { from: '.$milestone->getJavascriptTimestamp().', to: '.$milestone->getJavascriptTimestamp().' }, color: "#EBCCD1"';
            $this->milestonesMarkings .= '},';
        }

        return $this->milestonesMarkings;
    }

    /**
     * @param Campaign $campaign
     * @return ReportAnalyticsActivityFact[]
     */
    public function getActivities(Campaign $campaign){
        // Find all activities of this campaign that do have report data
        $qb = $this->em->getRepository('CampaignChainCoreBundle:ReportAnalyticsActivityFact')
            ->createQueryBuilder('fact')
            ->select('fact, activity')
            ->join('fact.activity', 'activity')
            ->where('fact.campaign = :campaignId')
            ->orderBy('activity.startDate', 'ASC')
            ->groupBy('fact.activity')
            ->setParameter('campaignId', $campaign->getId())
            ->getQuery();

        return $qb->getResult();
    }

    public function getCampaignDuration($campaign){
        // Get campaign duration in days
        $campaignStartDate = $campaign->getStartDate();
        $campaignEndDate = $campaign->getEndDate();
        return $this->campaignDuration = $campaignStartDate->diff($campaignEndDate)->format('%a');
    }

    public function getMilestones($campaign){
        $qb = $this->em->createQueryBuilder();
        $qb->select('m')
            ->from('CampaignChain\CoreBundle\Entity\Milestone', 'm')
            ->where('m.campaign = :campaignId')
            ->orderBy('m.startDate', 'ASC')
            ->setParameter('campaignId', $campaign->getId());
        $query = $qb->getQuery();
        return $this->milestones = $query->getResult();
    }

    public function getMetricNamesById(array $ids)
    {
        $qb = $this->em->getRepository('CampaignChainCoreBundle:ReportAnalyticsActivityMetric')
            ->createQueryBuilder('m');

        $results = $qb->select('m.id, m.name')
            ->where($qb->expr()->in('m.id', $ids))
            ->getQuery()
            ->getArrayResult();

        $tmp = [];
        foreach ($results as $result) {
            $tmp[$result['id']] = $result['name'];
        }

        return $tmp;
    }

    /**
     * Get the report data per activity.
     * @param Campaign $campaign
     * @param Activity $activity
     *
     * @return ReportAnalyticsActivityFact[]
     */
    public function getMetrics(Campaign $campaign, Activity $activity){
        $qb = $this->em->createQueryBuilder();
        $qb->select('r')
            ->from('CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact', 'r')
            ->where('r.activity = :activityId')
            ->andWhere('r.campaign = :campaignId')
            ->groupBy('r.metric')
            ->setParameter('activityId', $activity->getId())
            ->setParameter('campaignId', $campaign->getId());
        $query = $qb->getQuery();
        $this->dimensions = $query->getResult();
        return $this->dimensions;
    }

    /**
     * Get facts data per dimension.
     *
     * @param Campaign $campaign
     * @param Activity $activity
     * @param boolean  $percent
     *
     * @return array
     */
    public function getFacts(Campaign $campaign, Activity $activity, $percent = false){
        $qb = $this->em->createQueryBuilder();
        $qb->select('r.time, r.value, IDENTITY(r.metric) as metric')
            ->from('CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact', 'r')
            ->where('r.activity = :activityId')
            ->andWhere('r.campaign = :campaignId')
            ->orderBy('r.time', 'ASC')
            ->setParameter('activityId', $activity->getId())
            ->setParameter('campaignId', $campaign->getId());

        $query = $qb->getQuery();
        $facts = $query->getArrayResult();

        $factsData = [];
        $tmp = [];
        foreach ($facts as $fact) {
            $tmp[$fact['metric']][] = [
                $fact['time']->getTimestamp() * 1000,
                $fact['value'],
            ];
        }

        foreach (array_keys($tmp) as $k) {
            $factsData[$k]['data'] = $this->serializer->serialize($tmp[$k], 'json');
            $factsData[$k]['id'] = $k;
            if ($percent) {
                $factsData[$k]['percent'] = $this->getDimensionPercent($campaign, $activity, $k);
            }
        }

        return $factsData;
    }

    public function getDimensionPercent($campaign, $activity, $metric){
        // Get value of earliest and latest entry to calculate percentage
        $qb = $this->em->createQueryBuilder();
        $qb->select('r.value')
            ->from('CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact', 'r')
            ->where('r.activity = :activityId')
            ->andWhere('r.campaign = :campaignId')
            ->andWhere('r.metric = :metricId')
            ->orderBy('r.time', 'ASC')
            ->setMaxResults(1)
            ->setParameter('activityId', $activity->getId())
            ->setParameter('campaignId', $campaign->getId())
            ->setParameter('metricId', $metric);
        $query = $qb->getQuery();
        $startValue = $query->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder();
        $qb->select('r.value')
            ->from('CampaignChain\CoreBundle\Entity\ReportAnalyticsActivityFact', 'r')
            ->where('r.activity = :activityId')
            ->andWhere('r.campaign = :campaignId')
            ->andWhere('r.metric = :metricId')
            ->orderBy('r.time', 'DESC')
            ->setMaxResults(1)
            ->setParameter('activityId', $activity->getId())
            ->setParameter('campaignId', $campaign->getId())
            ->setParameter('metricId', $metric);
        $query = $qb->getQuery();
        $endValue = $query->getSingleScalarResult();

        // calculate percentage:
        if($startValue != 0){
            $percent = (($endValue - $startValue) / $startValue)*100;
        } else {
            $percent = 0;
        }

        //$data_percent = number_format( $percent * 100, 2 ) . '%';

        return $percent;
    }
}