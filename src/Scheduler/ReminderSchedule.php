<?php

namespace App\Scheduler;

use App\Message\SendBookingRemindersMessage;
use Symfony\Component\Console\Messenger\RunCommandMessage;
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
            // Le Scheduler se réveille toutes les heures pour lancer le script de rappel des réservations à venir
            RecurringMessage::every('1 hour', new SendBookingRemindersMessage()),
            // Avertissement des utilisateurs inactifs depuis 2 ans pour la suppression automatique de leur compte (RGPD)
            RecurringMessage::cron('15 10 * * *', new RunCommandMessage('app:warn-inactive-users')),
            // Purge automatique des comptes inactifs depuis 3 ans pour la conformité RGPD
            RecurringMessage::cron('45 10 * * *', new RunCommandMessage('app:purge-inactive-users'))
        );
    }
}
