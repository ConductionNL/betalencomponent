<?php


namespace App\Subscriber;


use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Service;
use App\Service\MollieService;
use App\Service\SumUpService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use PhpParser\Error;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class PaymentCreationSubscriber implements EventSubscriberInterface
{
    private $params;
    private $em;
    private $serializer;
    private $client;
    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, SerializerInterface $serializer)
    {

        $this->params = $params;
        $this->em = $em;
        $this->serializer = $serializer;
        $this->client = new Client();
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['payment', EventPriorities::PRE_SERIALIZE],
        ];
    }
    public function payment(ViewEvent $event)
    {
        $result = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        $route = $event->getRequest()->attributes->get('_route');
        var_dump($route);

        if($result instanceof Invoice && $method != 'DELETE')
        {
            $paymentService = $result->getOrganization()->getServices()[0];
            switch ($paymentService->getType()){
                case 'mollie':
                    $mollieService = new MollieService($paymentService);
                    $paymentUrl = $mollieService->createPayment($result, $event->getRequest());
                    $result->setPaymentUrl($paymentUrl);
                    break;
                case 'sumup':
                    $sumupService = new SumUpService($paymentService);
                    $paymentUrl = $sumupService->createPayment($result);
                    $result->setPaymentUrl($paymentUrl);
            }
            $json = $this->serializer->serialize(
                $result,
                'jsonhal', ['enable_max_depth'=>true]
            );

            $response = new Response(
                $json,
                Response::HTTP_OK,
                ['content-type' => 'application/json+hal']
            );
            $event->setResponse($response);

        }
        else
        {
            return;
        }
    }
//    public function payment(ViewEvent $event)
//    {
//        $result = $event->getControllerResult();
//        $method = $event->getRequest()->getMethod();
//        $route = $event->getRequest()->attributes->get('_route');
//
//        //var_dump($route);
//        if(!$result instanceof Payment || ($route != 'api_payments_post_webhook_collection' && $route != 'api_payments_post_collection')){
//            //var_dump('a');
//            return;
//        }
//        elseif($route=='api_payment_post_webhook_collection'){
//            $requestData = json_decode($event->getRequest()->getContent(),true);
//            $paymentProvider = $this->em->getRepository('App\Entity\Service')->find($requestData['serviceId']);
//            if($paymentProvider instanceof Service && $paymentProvider->getType() == 'mollie'){
//                $mollieService = new MollieService($requestData['paymentProvider']);
//                $payment = $mollieService->updatePayment($event->getRequest(), $this->em);
//            }
//            else{
//                return;
//            }
//        }else{
//            $requestData = json_decode($event->getRequest()->getContent(),true);
//            $paymentProvider = $this->em->getRepository('App\Entity\Service')->find($requestData['serviceId']);
//
//            if($paymentProvider instanceof Service) {
//                switch ($paymentProvider->getType()) {
//                    case 'mollie':
//                        $mollieService = new MollieService($paymentProvider);
//                        $payment = $mollieService->createPayment($event->getRequest());
//                        break;
//                    case 'sumup':
//                        $sumupService = new SumUpService($paymentProvider);
//                        $payment = $sumupService->createPayment($event->getRequest());
//                        break;
//                    default:
//                        return;
//                }
//            }
//            else
//                return;
//        }
//        $this->em->persist($payment);
//        $this->em->flush();
//
//
//        $json = $this->serializer->serialize(
//            $payment,
//            'jsonhal', ['enable_max_depth'=>true]
//        );
//
//        $response = new Response(
//            $json,
//            Response::HTTP_OK,
//            ['content-type' => 'application/json+hal']
//        );
//        $event->setResponse($response);
//    }
}