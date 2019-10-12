<?php

namespace Laravel\Cashier\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Payment;

class ConfirmPayment extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;

    /**
     * The URL that should be used as redirect target after confirmation.
     *
     * @var string|\Closure|null
     */
    public static $redirectsTo;

    /**
     * The PaymentIntent identifier.
     *
     * @var string
     */
    public $paymentId;

    /**
     * The payment amount.
     *
     * @var string
     */
    public $amount;

    /**
     * Create a new payment confirmation notification.
     *
     * @param  \Laravel\Cashier\Payment  $payment
     * @return void
     */
    public function __construct(Payment $payment)
    {
        $this->paymentId = $payment->id;
        $this->amount = $payment->amount();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = $this->paymentUrl($notifiable);

        if (static::$toMailCallback) {
            return (static::$toMailCallback)($this->amount, $url);
        }

        return (new MailMessage)
            ->greeting(__('Confirm your :amount payment', ['amount' => $this->amount]))
            ->line(__('Extra confirmation is needed to process your payment. Please continue to the payment page by clicking on the button below.'))
            ->action(__('Confirm Payment'), $url);
    }

    /**
     * Get the payment verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function paymentUrl($notifiable)
    {
        if ($redirect = static::$redirectsTo) {
            return route('cashier.payment', [
                'id' => $this->paymentId,
                'route' => is_callable($redirect) ? $redirect($notifiable) : $redirect,
            ]);
        }

        return route('cashier.payment', ['id' => $this->paymentId]);
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }

    /**
     * Set an URL that should be used for redirection after successful payment verification.
     *
     * @param  string|\Closure  $callback
     * @return void
     */
    public static function redirectsTo($url)
    {
        static::$redirectsTo = $url;
    }
}
