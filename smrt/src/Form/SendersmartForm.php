<?php

namespace Drupal\smrt\Form;

use Drupal\Core\Form\FormBase;                   // Базовый класс Form API
use Drupal\Core\Form\FormStateInterface;              // Класс отвечает за обработку данных

/**
 * Наследуемся от базового класса Form API
 * @see \Drupal\Core\Form\FormBase
 */
class SendersmartForm extends FormBase {

    // метод, который отвечает за саму форму - кнопки, поля
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['first_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('First Name'),
            '#required' => TRUE,
        ];

        $form['last_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Last Name'),
            '#required' => TRUE,
        ];
        $form['subject'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Subject'),
            '#required' => TRUE,
        ];

        $form['message'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Message'),
            '#required' => TRUE,
        ];
        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('E-mail'),
            '#required' => TRUE,
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Send'),
        ];

        return $form;
    }

    // метод, который будет возвращать название формы
    public function getFormId() {
        return 'sendersmart_smrt_form';
    }

    // ф-я валидации
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $email = $form_state->getValue('email');
        if(filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $form_state->setErrorByName('email', $this->t('Not valid email.'));
        }
    }

    // действия по сабмиту
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $email = $form_state->getValue('email');
        $first_name = $form_state->getValue('first_name');
        $last_name = $form_state->getValue('last_name');
        $message = $form_state->getValue('message');
        $subject = $form_state->getValue('subject');

        if($this->send_message_smrt($email, $first_name, $last_name, $subject, $message)) {
            drupal_set_message(t('Сообщение отправлено на почту: %email.', ['%email' => $email]));
            $this->create_contact_hubspot($email,$first_name,$last_name);
            \Drupal::logger('mail-log')->notice(t('An email notification has been sent to @email ', array('@email' => $email)));
        } else {
            drupal_set_message(t('Ошибка отправки сообщения'));
        }

    }

    public function create_contact_hubspot($email, $first_name, $last_name) {
        $arr = array(
            'properties' => array(
                array(
                    'property' => 'email',
                    'value' => $email
                ),
                array(
                    'property' => 'firstname',
                    'value' => $first_name
                ),
                array(
                    'property' => 'lastname',
                    'value' => $last_name
                ),
            )
        );
        $post_json = json_encode($arr);
        $endpoint = 'https://api.hubapi.com/contacts/v1/contact?hapikey=b04dc8d9-86d6-4e8d-a506-10d6a0e5425e';
        $ch = @curl_init();
        @curl_setopt($ch, CURLOPT_POST, true);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
        @curl_setopt($ch, CURLOPT_URL, $endpoint);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = @curl_exec($ch);
        $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errors = curl_error($ch);
        @curl_close($ch);
    }

    public function send_message_smrt($email, $first_name, $last_name, $subject, $html) {
        $send_mail = new \Drupal\Core\Mail\Plugin\Mail\PhpMail(); // this is used to send HTML emails
        $from = 'admin@site.ru';
        $to = $email;
        $message['headers'] = array(
            'content-type' => 'text/html',
            'MIME-Version' => '1.0',
            'reply-to' => $from,
            'from' => $first_name.' '.$last_name.' <'.$from.'>'
        );
        $message['to'] = $to;
        $message['subject'] = $subject;
        $message['body'] = $html;
        return $send_mail->mail($message);
    }

}