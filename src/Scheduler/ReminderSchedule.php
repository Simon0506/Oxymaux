<?php

namespace App\Scheduler;

use App\Message\SendBookingRemindersMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class ReminderSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->with(
            // Le Scheduler se réveille toutes les heures pour lancer le script
            RecurringMessage::every('1 hour', new SendBookingRemindersMessage())
        );
    }
}
