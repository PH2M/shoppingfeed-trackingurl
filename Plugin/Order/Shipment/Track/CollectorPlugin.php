<?php

namespace ShoppingFeed\TrackingUrl\Plugin\Order\Shipment\Track;

use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\ShipmentInterface as ShipmentInterface;
use Magento\Shipping\Model\Order\Track as SalesOrderTrack;
use Magento\Shipping\Model\ResourceModel\Order\Track\CollectionFactory as SalesOrderTrackCollectionFactory;
use ShoppingFeed\Manager\Model\Sales\Order\Shipment\Track as ShipmentTrack;
use ShoppingFeed\Manager\Model\Sales\Order\Shipment\TrackFactory as ShipmentTrackFactory;
use ShoppingFeed\Manager\Model\Sales\Order\Shipment\Track\Collector;

class CollectorPlugin
{
    /**
     * @var SalesOrderTrackCollectionFactory
     */
    private $salesOrderTrackCollectionFactory;

    /**
     * @var ShipmentTrackFactory
     */
    private $shipmentTrackFactory;

    /**
     * @param SalesOrderTrackCollectionFactory $salesOrderTrackCollectionFactory
     * @param ShipmentTrackFactory $shipmentTrackFactory
     */
    public function __construct(
        SalesOrderTrackCollectionFactory $salesOrderTrackCollectionFactory,
        ShipmentTrackFactory $shipmentTrackFactory
    ) {
        $this->salesOrderTrackCollectionFactory = $salesOrderTrackCollectionFactory;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
    }

    /**
     * @param Collector $subject
     * @param callable $proceed
     * @param ShipmentInterface $shipment
     * @return ShipmentTrack[]
     */
    public function aroundGetShipmentTracks(Collector $subject, callable $proceed, ShipmentInterface $shipment)
    {
        $salesOrderTrackCollection = $this->salesOrderTrackCollectionFactory->create();
        $salesOrderTrackCollection->setShipmentFilter($shipment->getEntityId());

        $shipmentTracks = [];

        /** @var SalesOrderTrack $salesOrderTrack */
        foreach ($salesOrderTrackCollection as $salesOrderTrack) {
            $salesOrderTrack->setShipment($shipment);

            try {
                $trackingDetail = $salesOrderTrack->getNumberDetail();

                if ($trackingDetail instanceof DataObject) {
                    $carrierCode = trim($trackingDetail->getCarrier());
                    $carrierTitle = trim($trackingDetail->getCarrierTitle());
                    $trackingNumber = trim($trackingDetail->getTracking());
                    $trackingUrl = trim($trackingDetail->getUrl());
                } else {
                    throw new \Exception();
                }
            } catch (\Exception $e) {
                $carrierCode = '';
                $carrierTitle = '';
                $trackingNumber = '';
                $trackingUrl = '';
            }

            if (empty($carrierCode)) {
                $carrierCode = trim($salesOrderTrack->getCarrierCode());
            }

            if (empty($carrierTitle)) {
                $carrierTitle = trim($salesOrderTrack->getTitle());
            }

            if (empty($trackingNumber)) {
                $trackingNumber = trim($salesOrderTrack->getTrackNumber());
            }

            if (empty($trackingUrl)) {
                $trackingUrl = (is_callable([ $salesOrderTrack, 'getUrl' ]) && $salesOrderTrack->getUrl()) ? trim($salesOrderTrack->getUrl()) : '';
            }

            $relevance = Collector::DEFAULT_BASE_TRACK_RELEVANCE;

            if (!empty($carrierCode)) {
                $relevance += Collector::DEFAULT_FILLED_CARRIER_CODE_RELEVANCE;
            } else {
                $carrierCode = Collector::DEFAULT_CARRIER_CODE;
            }

            if (!empty($carrierTitle)) {
                $relevance += Collector::DEFAULT_FILLED_CARRIER_TITLE_RELEVANCE;
            } else {
                $carrierTitle = __('Carrier');
            }

            if (!empty($trackingNumber)) {
                $relevance += Collector::DEFAULT_FILLED_TRACKING_NUMBER_RELEVANCE;
            }

            if (!empty($trackingUrl)) {
                $relevance += Collector::DEFAULT_FILLED_TRACKING_URL_RELEVANCE;
            }

            $shipmentTracks[] = $this->shipmentTrackFactory->create(
                [
                    'carrierCode' => $carrierCode,
                    'carrierTitle' => $carrierTitle,
                    'trackingNumber' => $trackingNumber,
                    'trackingUrl' => $trackingUrl,
                    'relevance' => $relevance,
                ]
            );
        }

        if (empty($shipmentTracks)) {
            $shipmentTracks[] = $this->shipmentTrackFactory->create(
                [
                    'carrierCode' => Collector::DEFAULT_CARRIER_CODE,
                    'carrierTitle' => '',
                    'trackingNumber' => '',
                    'trackingUrl' => '',
                    'relevance' => '0',
                ]
            );
        }

        return $shipmentTracks;
    }
}
