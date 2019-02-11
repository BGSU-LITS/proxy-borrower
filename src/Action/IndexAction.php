<?php
/**
 * Form Action Class
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2016 Bowling Green State University Libraries
 * @license MIT
 * @package Proxy Borrower
 */

namespace App\Action;

use \App\Exception\RequestException;

use Slim\Flash\Messages;
use Slim\Views\Twig;
use Swift_Mailer;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * A class to be invoked for the form action.
 */
class IndexAction
{
    /**
     * Flash messenger.
     * @var Messages
     */
    private $flash;

    /**
     * View renderer.
     * @var Twig
     */
    private $view;

    /**
     * Email sender.
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * Address email should be sent to.
     * @var string
     */
    private $mailTo;

    /**
     * Address email should be carbon copied to.
     * @var string
     */
    private $mailCc;

    private $fields = [
        'faculty_name',
        'faculty_id',
        'faculty_email',
        'designee_name',
        'designee_id',
        'designee_email',
        'signature',
        'department',
        'expire'
    ];

    /**
     * Construct the action with objects and configuration.
     * @param Messages $flash Flash messenger
     * @param Twig $view View renderer.
     * @param Swift_Mailer $mailer Email sender.
     * @param string $mailTo Address email should be sent to.
     * @param string $mailCc Address email should be carbon copied to.
     */
    public function __construct(
        Messages $flash,
        Twig $view,
        Swift_Mailer $mailer,
        $mailTo,
        $mailCc
    ) {
        $this->flash = $flash;
        $this->view = $view;
        $this->mailer = $mailer;
        $this->mailTo = $mailTo;
        $this->mailCc = $mailCc;
    }

    /**
     * Method called when class is invoked as an action.
     * @param Request $req The request for the action.
     * @param Response $res The response from the action.
     * @param array $args The arguments for the action.
     * @return Response The response from the action.
     */
    public function __invoke(Request $req, Response $res, array $args)
    {
        $args['messages'] = $this->messages();

        if ($req->getMethod() === 'POST') {
            foreach ($this->fields as $key) {
                $args[$key] = $req->getParam($key);
            }

            if (!empty($args['expire'])) {
                $args['expire'] = date('Y-m-d', strtotime($args['expire']));
            }

            try {
                $this->validateCsrf($req);
                $this->validateRequest($args);
                $this->sendEmail($args);

                $this->flash->addMessage(
                    'success',
                    'Your research proxy borrower form has been sent.'.
                    ' You may send another request below.'
                );

                return $res->withStatus(302)->withHeader(
                    'Location',
                    $req->getUri()->getBasePath()
                );
            } catch (RequestException $exception) {
                $args['messages'][] = [
                    'level' => 'danger',
                    'message' => $exception->getMessage()
                ];
            }
        }

        // Render form template.
        return $this->view->render($res, 'index.html.twig', $args);
    }

    private function messages()
    {
        $result = [];

        foreach (['success', 'danger'] as $level) {
            $messages = $this->flash->getMessage($level);

            if (is_array($messages)) {
                foreach ($messages as $message) {
                    $result[] = [
                        'level' => $level,
                        'message' => $message
                    ];
                }
            }
        }

        return $result;
    }

    private function sendEmail(array $args)
    {
        try {
            $mailCc = [
                $args['faculty_email'],
                $args['designee_email']
            ];

            if (!empty($this->mailCc)) {
                $mailCc[] = $this->mailCc;
            }

            $message = $this->mailer->createMessage()
                ->setSubject('Research Proxy Borrower Form')
                ->setFrom($args['faculty_email'])
                ->setTo($this->mailTo)
                ->setCc($mailCc)
                ->setBody(
                    $this->view->fetch('email.html.twig', $args),
                    'text/html'
                );

            if (!$this->mailer->send($message)) {
                throw new RequestException(
                    'Could not send email to the address specified.'
                );
            }
        } catch (\Swift_SwiftException $e) {
            throw new RequestException(
                'An unexpected error occurred. Please try again.'
            );
        }
    }

    private function validateCsrf(Request $req)
    {
        if ($req->getAttribute('csrf_failed')) {
            throw new RequestException(
                'Your request timed out. Please try again.'
            );
        }
    }

    private function validateRequest(array $args)
    {
        foreach ($this->fields as $key) {
            if (empty($args[$key])) {
                throw new RequestException(
                    'You must specify all fields.'
                );
            }
        }

        if (!preg_match('/^\d{10}$/', $args['faculty_id'])) {
            throw new RequestException(
                'You must specify a valid faculty/staff member’s ID number.'
            );
        }

        if (!preg_match('/^\d{10}$/', $args['designee_id'])) {
            throw new RequestException(
                'You must specify a valid designee’s ID number.'
            );
        }

        if (!filter_var($args['faculty_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RequestException(
                'You must specify a valid faculty/staff member’s email.'
            );
        }

        if (!filter_var($args['designee_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RequestException(
                'You must specify a valid designee’s email.'
            );
        }

        if ($args['expire'] < date('Y-m-d')) {
            throw new RequestException(
                'You must specify an expiration date in the future.'
            );
        }

        if ($args['expire'] > date('Y-m-d', strtotime('+1 year'))) {
            throw new RequestException(
                'You must specify an expiration date within the next year.'
            );
        }
    }
}
