<?php

namespace App\Controllers;

use App\Helpers\ClinicFormValidation;
use App\Services\Assessment\AssessmentService;
use App\Services\Billing\BillingService;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\ReportsService;
use App\Services\Inventory\InventoryService;
use App\Services\StaffSchedule\StaffScheduleService;
use App\Services\TherapyNote\TherapyNoteService;

class DashboardController extends BaseController
{
    public function index()
    {
        return view('dashboard/index', (new DashboardOverviewService())->overview());
    }

    public function reports()
    {
        return view('dashboard/reports', ['reportTypes' => ReportsService::REPORT_TYPES]);
    }

    public function reportsAnalytics()
    {
        return view('dashboard/reports_analytics', (new ReportsService())->analytics());
    }

    public function export()
    {
        $type = strtolower((string) ($this->request->getGet('type') ?? 'patient-summary'));

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="rehabplus-' . $type . '.csv"')
            ->setBody((new ReportsService())->exportCsv($type));
    }

    public function billing()
    {
        return view('dashboard/billing', (new BillingService())->overview());
    }

    public function saveBilling()
    {
        $form = ClinicFormValidation::billing($this->request);
        if ($form['error']) {
            return redirect()->to(site_url('billing'))->with('error', $form['error']);
        }

        (new BillingService())->add($form['data']);

        return redirect()->to(site_url('billing'))->with('success', 'Payment record added successfully.');
    }

    public function inventory()
    {
        return view('dashboard/inventory', (new InventoryService())->overview());
    }

    public function saveInventory()
    {
        $form = ClinicFormValidation::inventory($this->request);
        if ($form['error']) {
            return redirect()->to(site_url('inventory'))->with('error', $form['error']);
        }

        (new InventoryService())->add($form['data']);

        return redirect()->to(site_url('inventory'))->with('success', 'Inventory item added successfully.');
    }

    public function schedule()
    {
        $weekOffset = (int) ($this->request->getGet('week_offset') ?? 0);

        return view('dashboard/schedule', (new StaffScheduleService())->overview($weekOffset));
    }

    public function saveSchedule()
    {
        $form = ClinicFormValidation::shift($this->request);
        if ($form['error']) {
            return redirect()->to(site_url('schedule'))->with('error', $form['error']);
        }

        if (! (new StaffScheduleService())->addShift($form['data'])) {
            return redirect()->to(site_url('schedule'))->with('error', 'Schedule conflict detected: this staff member already has a shift at that time, or the room is already assigned to another staff member.');
        }

        return redirect()->to(site_url('schedule'))->with('success', 'Staff schedule added successfully.');
    }

    public function saveTimeOff()
    {
        $form = ClinicFormValidation::timeOff($this->request);
        if ($form['error']) {
            return redirect()->to(site_url('schedule'))->with('error', $form['error']);
        }

        (new StaffScheduleService())->addTimeOff($form['data']);

        return redirect()->to(site_url('schedule'))->with('success', 'Time off entry added successfully.');
    }

    public function notes()
    {
        return view('dashboard/notes', (new TherapyNoteService())->overview());
    }

    public function saveNotes()
    {
        $form = ClinicFormValidation::therapyNote($this->request);
        if ($form['error']) {
            return redirect()->to(site_url('notes'))->with('error', $form['error']);
        }

        (new TherapyNoteService())->add($form['data']);

        return redirect()->to(site_url('notes'))->with('success', 'Therapy note added successfully.');
    }

    public function assessments()
    {
        return view('dashboard/assessments', (new AssessmentService())->overview());
    }

    public function saveAssessments()
    {
        $form = ClinicFormValidation::assessment($this->request);
        if ($form['error']) {
            return redirect()->to(site_url('assessments'))->with('error', $form['error']);
        }

        (new AssessmentService())->add($form['data']);

        return redirect()->to(site_url('assessments'))->with('success', 'Assessment added successfully.');
    }
}
