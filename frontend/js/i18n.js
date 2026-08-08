/**
 * SOMA LMS - Global i18n Translation Engine
 * Single source of truth for ALL text in the system.
 * Supports: en (English - Default), sw (Kiswahili - Tanzania)
 */

export const translations = {
    en: {
        // ─── SIDEBAR MENU LABELS ─────────────────────────────────────────────
        menu: {
            dashboard:           'Dashboard',
            schools:             'Schools',
            regionalOffices:     'Regional Offices',
            academicTemplates:   'Academic Templates',
            platformUsers:       'Platform Users',
            databaseBackups:     'Database Backups',
            reports:             'Reports',
            settings:            'Settings',
            myProfile:           'My Profile',
            studentsDirectory:   'Students Directory',
            teachersStaffs:      'Teachers & Staffs',
            classGuiders:        'Class Guiders',
            subjectAllocations:  'Subject Allocations',
            academics:           'Academics',
            classrooms:          'Classrooms',
            timetables:          'Timetables',
            myAssignedClasses:   'My Assigned Classes',
            mySchedule:          'My Schedule',
            markEntry:           'Mark Entry Workspace',
            attendanceTracker:   'Attendance Tracker',
            parentDesk:          'Parent Desk',
            mySubjects:          'My Subjects',
            grades:              'Grades',
            myChildren:          'My Children',
        },

        // ─── TOPBAR ──────────────────────────────────────────────────────────
        topbar: {
            platformName: 'Soma LMS Platform',
            howdy:        'Howdy,',
            logout:       'Logout',
            langEn:       '🇺🇸 EN',
            langSw:       '🇹🇿 SW',
        },

        // ─── COMMON / SHARED ─────────────────────────────────────────────────
        common: {
            loading:      'Loading...',
            save:         'Save',
            cancel:       'Cancel',
            edit:         'Edit',
            delete:       'Delete',
            view:         'View',
            search:       'Search...',
            filter:       'Filter',
            submit:       'Submit',
            close:        'Close',
            yes:          'Yes',
            no:           'No',
            back:         'Back',
            next:         'Next',
            confirm:      'Confirm',
            success:      'Success',
            error:        'Error',
            warning:      'Warning',
            noData:       'No records found.',
            allYears:     'All Years',
            allMonths:    'All Months',
            allStreams:   'All Classroom Streams',
            actions:      'Actions',
            status:       'Status',
            name:         'Name',
            date:         'Date',
            year:         'Year',
            month:        'Month',
            active:       'Active',
            inactive:     'Inactive',
            present:      'Present',
            absent:       'Absent',
            excused:      'Excused',
            total:        'Total',
            january:      'January',
            february:     'February',
            march:        'March',
            april:        'April',
            may:          'May',
            june:         'June',
            july:         'July',
            august:       'August',
            september:    'September',
            october:      'October',
            november:     'November',
            december:     'December',
            remarks:      'Remarks',
            noRemarks:    'No remarks recorded',
            regId:        'Registration ID',
            fullName:     'Full Name',
        },

        // ─── ATTENDANCE PAGE ─────────────────────────────────────────────────
        attendance: {
            pageTitle:          'Daily Attendance Register',
            hubTitle:           'MASTER DAILY ATTENDANCE HUB',
            tabEntry:           '📋 Daily Roll-Call Register',
            tabHistory:         '📜 Attendance History Logs',
            selectClassroom:    '🏫 Select Classroom Stream:',
            selectDate:         '📆 Select Date:',
            modeLocked:         '🔒 Saved & Sealed',
            modeEdit:           '✏️ Edit Mode Active',
            totalRoster:        'TOTAL ROSTER',
            rosterTitle:        '👥 Student Roll-Call Roster Sheet',
            searchPlaceholder:  '🔍 Search student by name or ID...',
            colNum:             '#',
            colRegId:           'Student Reg ID',
            colName:            'Full Name',
            colStatus:          'Attendance Status (Toggle)',
            colStatusLocked:    'Attendance Status',
            remarksLabel:       '📝 Additional Remarks / Notes (Optional):',
            remarksPlaceholder: 'Enter optional remarks for this date (e.g. John had a family emergency)...',
            savedNotice:        '🔒 Attendance for this date has been cataloged & sealed. Click Edit to modify.',
            btnEdit:            '✏️ Edit Roll-Call',
            btnSave:            '💾 SAVE DAILY ATTENDANCE',
            btnCancel:          '❌ Cancel Edit',
            btnFilter:          '🔍 Filter History',
            historyTitle:       '📜 Saved Attendance Roll-Call Logs',
            histColDate:        'Date & Day',
            histColClass:       'Classroom & Year',
            histColBreakdown:   'Student Attendance Breakdown',
            histColRemarks:     'Remarks',
            histColAction:      'Action',
            noStudents:         'No active students found in this classroom roster.',
            noHistory:          'No attendance logs found for the selected filters.',
            filterYear:         '📅 Academic Year:',
            filterMonth:        '🗓️ Month:',
            filterRoom:         '🏫 Classroom Stream:',
            accessDeniedTitle:  'Access Restricted: Class Guider / Form Master Only',
            savedMsg:           'Attendance saved successfully!',
            errorSaving:        'Error saving attendance. Please try again.',
            networkError:       'Network error. Please check your connection.',
            saving:             'Saving...',
        },

        // ─── PROFILE PAGE ─────────────────────────────────────────────────────
        profile: {
            pageTitle:          'My Profile',
            fullName:           'Full Name',
            regId:              'Reg Code / Staff ID',
            role:               'Role',
            locked:             '🔒 Lock',
            editableSection:    '✏️ Editable Information',
            phone:              'Phone Number',
            email:              'Email Address',
            gender:             'Gender',
            male:               'Male',
            female:             'Female',
            btnSave:            '💾 Save Profile Changes',
            securitySection:    '🔒 Security & Password',
            currentPassword:    'Current Password',
            newPassword:        'New Password',
            confirmPassword:    'Confirm New Password',
            btnChangePassword:  '🔑 Change Password Now',
        },

        // ─── DASHBOARD SHARED ─────────────────────────────────────────────────
        dashboard: {
            welcomeBack:        'Welcome back,',
            quickStats:         'Quick Statistics',
            recentActivity:     'Recent Activity',
            noActivity:         'No recent activity.',
        },

        // ─── TEACHER DASHBOARD ────────────────────────────────────────────────
        teacherDashboard: {
            mySubjects:         'My Subjects',
            totalStudents:      'Total Students',
            formMasterClass:    'Form Master Class',
            todayPeriods:       "Today's Periods",
            myTodaySchedule:    "Today's Teaching Schedule",
            day:                'Day',
            stream:             'Stream',
            subject:            'Subject',
            period:             'Period',
            time:               'Time',
            noSchedule:         'No teaching periods scheduled for today.',
        },

        // ─── HEADMASTER DASHBOARD ──────────────────────────────────────────────
        headmasterDashboard: {
            totalStudents:      'Total Students',
            totalTeachers:      'Total Teachers',
            totalClasses:       'Total Classrooms',
            averageAttendance:  'Avg. Attendance Rate',
        },
    },

    // ═══════════════════════════════════════════════════════════════════════════
    sw: {
        // ─── SIDEBAR MENU LABELS ─────────────────────────────────────────────
        menu: {
            dashboard:           'Dashibodi',
            schools:             'Shule',
            regionalOffices:     'Ofisi za Mikoa',
            academicTemplates:   'Mifano ya Kitaaluma',
            platformUsers:       'Watumiaji wa Mfumo',
            databaseBackups:     'Nakala Rudufu za Data',
            reports:             'Ripoti',
            settings:            'Mipangilio',
            myProfile:           'Wasifu Wangu',
            studentsDirectory:   'Orodha ya Wanafunzi',
            teachersStaffs:      'Walimu na Wafanyakazi',
            classGuiders:        'Walimu Welekezaji',
            subjectAllocations:  'Ugawaji wa Masomo',
            academics:           'Masomo ya Kitaaluma',
            classrooms:          'Madarasa',
            timetables:          'Jedwali la Masaa',
            myAssignedClasses:   'Madarasa Yangu',
            mySchedule:          'Ratiba Yangu',
            markEntry:           'Ingiza Alama',
            attendanceTracker:   'Kumbukumbu za Mahudhurio',
            parentDesk:          'Dawati la Wazazi',
            mySubjects:          'Masomo Yangu',
            grades:              'Alama na Daraja',
            myChildren:          'Watoto Wangu',
        },

        // ─── TOPBAR ──────────────────────────────────────────────────────────
        topbar: {
            platformName: 'Mfumo wa SOMA LMS',
            howdy:        'Karibu,',
            logout:       'Toka',
            langEn:       '🇺🇸 EN',
            langSw:       '🇹🇿 SW',
        },

        // ─── COMMON / SHARED ─────────────────────────────────────────────────
        common: {
            loading:      'Inapakia...',
            save:         'Hifadhi',
            cancel:       'Ghairi',
            edit:         'Hariri',
            delete:       'Futa',
            view:         'Angalia',
            search:       'Tafuta...',
            filter:       'Chuja',
            submit:       'Wasilisha',
            close:        'Funga',
            yes:          'Ndiyo',
            no:           'Hapana',
            back:         'Rudi',
            next:         'Endelea',
            confirm:      'Thibitisha',
            success:      'Imefanikiwa',
            error:        'Hitilafu',
            warning:      'Onyo',
            noData:       'Hakuna rekodi zilizopatikana.',
            allYears:     'Miaka Yote',
            allMonths:    'Miezi Yote',
            allStreams:   'Madarasa Yote',
            actions:      'Vitendo',
            status:       'Hali',
            name:         'Jina',
            date:         'Tarehe',
            year:         'Mwaka',
            month:        'Mwezi',
            active:       'Amilifu',
            inactive:     'Hafifu',
            present:      'Yupo',
            absent:       'Hayupo',
            excused:      'Ana Udhuru',
            total:        'Jumla',
            january:      'Januari',
            february:     'Februari',
            march:        'Machi',
            april:        'Aprili',
            may:          'Mei',
            june:         'Juni',
            july:         'Julai',
            august:       'Agosti',
            september:    'Septemba',
            october:      'Oktoba',
            november:     'Novemba',
            december:     'Desemba',
            remarks:      'Maelezo',
            noRemarks:    'Bila maelezo',
            regId:        'Namba ya Usajili',
            fullName:     'Jina Kamili',
        },

        // ─── ATTENDANCE PAGE ─────────────────────────────────────────────────
        attendance: {
            pageTitle:          'Daftari la Mahudhurio ya Kila Siku',
            hubTitle:           'KITUO cha MAHUDHURIO YA KILA SIKU',
            tabEntry:           '📋 Rekodi Mahudhurio ya Leo',
            tabHistory:         '📜 Historia ya Mahudhurio',
            selectClassroom:    '🏫 Chagua Darasa:',
            selectDate:         '📆 Chagua Tarehe:',
            modeLocked:         '🔒 Imefungwa na Kuhifadhiwa',
            modeEdit:           '✏️ Hali ya Kuhariri',
            totalRoster:        'WANAFUNZI WOTE',
            rosterTitle:        '👥 Orodha ya Wanafunzi',
            searchPlaceholder:  '🔍 Tafuta mwanafunzi kwa jina au namba...',
            colNum:             '#',
            colRegId:           'Namba ya Usajili',
            colName:            'Jina Kamili',
            colStatus:          'Hali ya Mahudhurio (Bonyeza Kubadilisha)',
            colStatusLocked:    'Hali ya Mahudhurio',
            remarksLabel:       '📝 Maelezo ya Ziada (Hiari):',
            remarksPlaceholder: 'Weka maelezo ya ziada (mfano: Baraka amepatwa na dharura ya kifamilia)...',
            savedNotice:        '🔒 Mahudhurio ya siku hii yamehifadhiwa. Bonyeza Hariri kubadilisha.',
            btnEdit:            '✏️ Hariri Mahudhurio',
            btnSave:            '💾 HIFADHI MAHUDHURIO',
            btnCancel:          '❌ Ghairi',
            btnFilter:          '🔍 Chuja Historia',
            historyTitle:       '📜 Kumbukumbu za Mahudhurio Zilizohifadhiwa',
            histColDate:        'Tarehe na Siku',
            histColClass:       'Darasa na Mwaka',
            histColBreakdown:   'Muhtasari wa Mahudhurio',
            histColRemarks:     'Maelezo',
            histColAction:      'Kitendo',
            noStudents:         'Hakuna wanafunzi waliopo kwenye darasa hili.',
            noHistory:          'Hakuna kumbukumbu za mahudhurio kwa vichujio ulivyochagua.',
            filterYear:         '📅 Mwaka wa Masomo:',
            filterMonth:        '🗓️ Mwezi:',
            filterRoom:         '🏫 Darasa:',
            accessDeniedTitle:  'Imezuiwa: Ni Mwalimu wa Darasa Tu',
            savedMsg:           'Mahudhurio yamehifadhiwa kikamilifu!',
            errorSaving:        'Hitilafu wakati wa kuhifadhi. Jaribu tena.',
            networkError:       'Hitilafu ya mtandao. Angalia muunganisho wako.',
            saving:             'Inahifadhi...',
        },

        // ─── PROFILE PAGE ─────────────────────────────────────────────────────
        profile: {
            pageTitle:          'Wasifu Wangu',
            fullName:           'Jina Kamili',
            regId:              'Namba ya Usajili / Namba ya Mfanyakazi',
            role:               'Wajibu',
            locked:             '🔒 Imefungwa',
            editableSection:    '✏️ Taarifa Zinazoweza Kubadilishwa',
            phone:              'Namba ya Simu',
            email:              'Barua Pepe',
            gender:             'Jinsia',
            male:               'Mwanaume',
            female:             'Mwanamke',
            btnSave:            '💾 Hifadhi Mabadiliko ya Wasifu',
            securitySection:    '🔒 Usalama na Nenosiri',
            currentPassword:    'Nenosiri la Sasa',
            newPassword:        'Nenosiri Jipya',
            confirmPassword:    'Thibitisha Nenosiri Jipya',
            btnChangePassword:  '🔑 Badili Nenosiri Sasa',
        },

        // ─── DASHBOARD SHARED ─────────────────────────────────────────────────
        dashboard: {
            welcomeBack:        'Karibu tena,',
            quickStats:         'Takwimu za Haraka',
            recentActivity:     'Shughuli za Hivi Karibuni',
            noActivity:         'Hakuna shughuli za hivi karibuni.',
        },

        // ─── TEACHER DASHBOARD ────────────────────────────────────────────────
        teacherDashboard: {
            mySubjects:         'Masomo Yangu',
            totalStudents:      'Jumla ya Wanafunzi',
            formMasterClass:    'Darasa Ninaloliongoza',
            todayPeriods:       'Vipindi vya Leo',
            myTodaySchedule:    'Ratiba ya Kufundisha Leo',
            day:                'Siku',
            stream:             'Darasa',
            subject:            'Somo',
            period:             'Kipindi',
            time:               'Muda',
            noSchedule:         'Hakuna vipindi vilivyopangwa kwa leo.',
        },

        // ─── HEADMASTER DASHBOARD ──────────────────────────────────────────────
        headmasterDashboard: {
            totalStudents:      'Jumla ya Wanafunzi',
            totalTeachers:      'Jumla ya Walimu',
            totalClasses:       'Jumla ya Madarasa',
            averageAttendance:  'Kiwango cha Wastani cha Mahudhurio',
        },
    }
};

/**
 * Get the current active language key ('en' or 'sw')
 */
export function getLang() {
    return localStorage.getItem('soma_lang') || 'en';
}

/**
 * Get translations object for current language.
 * Returns English as fallback if key missing.
 */
export function t() {
    const lang = getLang();
    return translations[lang] || translations['en'];
}

/**
 * Translate a deeply nested key using dot notation, e.g. 'menu.dashboard'
 */
export function tr(key) {
    const lang = getLang();
    const parts = key.split('.');
    let val = translations[lang];
    for (const p of parts) {
        val = val?.[p];
        if (val === undefined) break;
    }
    // Fallback to English
    if (val === undefined) {
        let fallback = translations['en'];
        for (const p of parts) {
            fallback = fallback?.[p];
            if (fallback === undefined) break;
        }
        return fallback ?? key;
    }
    return val;
}

/**
 * Apply translations to all elements with data-i18n attribute.
 * Usage in HTML: <span data-i18n="menu.dashboard">Dashboard</span>
 */
export function applyTranslations(root = document) {
    root.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        const translated = tr(key);
        if (translated) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                const attr = el.getAttribute('data-i18n-attr') || 'placeholder';
                el.setAttribute(attr, translated);
            } else {
                el.textContent = translated;
            }
        }
    });
}
