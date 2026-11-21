<?php

declare(strict_types=1);

/*
 * Role → permission map (spec section 5). AuthServiceProvider registers one Gate per key in 'permissions'.
 * Controllers and views check access with can: and @can, never by comparing the role name.
 *
 * The area.* keys decide which panel a role may open (spec section 8). They are not in the spec's matrix,
 * and were added so the panel middleware can use the same Gates.
 */

return [

    'permissions' => [
        'client.view',
        'client.create',
        'client.update',
        'client.delete',
        'medical.view',
        'medical.update',
        'medical.view_alerts',
        'appointment.view_all',
        'appointment.view_today',
        'appointment.view_own',
        'appointment.manage',
        'session.record',
        'session.view',
        'measurement.record',
        'measurement.review',
        'measurement.view',
        'note.manage',
        'payment.view',
        'payment.record',
        'payment.reverse',
        'package.manage',
        'report.admin',
        'audit.view',
        'user.manage',
        'catalog.manage',
        'sms.templates.manage',
        'sms.log.view',
        'settings.manage',
        'area.admin',
        'area.operator',
        'area.specialist',
    ],

    'roles' => [
        'admin' => [
            'client.view',
            'client.create',
            'client.update',
            'client.delete',
            'medical.view',
            'medical.update',
            'medical.view_alerts',
            'appointment.view_all',
            'appointment.view_today',
            'appointment.manage',
            'session.record',
            'session.view',
            'measurement.record',
            'measurement.review',
            'measurement.view',
            'note.manage',
            'payment.view',
            'payment.record',
            'payment.reverse',
            'package.manage',
            'report.admin',
            'audit.view',
            'user.manage',
            'catalog.manage',
            'sms.templates.manage',
            'sms.log.view',
            'settings.manage',
            'area.admin',
        ],

        'secretary' => [
            'client.view',
            'client.create',
            'client.update',
            'medical.view_alerts',
            'appointment.view_all',
            'appointment.view_today',
            'appointment.manage',
            'session.view',
            'payment.view',
            'payment.record',
            'package.manage',
            'sms.log.view',
            'area.admin',
        ],

        // Operator sees clients booked today only. That is enforced by a scoped query in S2, not by this list.
        'operator' => [
            'client.view',
            'medical.view_alerts',
            'appointment.view_today',
            'session.record',
            'session.view',
            'measurement.record',
            'measurement.view',
            'area.operator',
        ],

        'specialist' => [
            'client.view',
            'client.create',
            'client.update',
            'medical.view',
            'medical.update',
            'medical.view_alerts',
            'appointment.view_today',
            'appointment.view_own',
            'session.record',
            'session.view',
            'measurement.record',
            'measurement.review',
            'measurement.view',
            'note.manage',
            'area.specialist',
        ],
    ],
];
