<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Service_Catalog 07.09.2025.xlsx" faylidagi 25 ta xizmat: har biri uchun
 * `services` + `service_offerings` yozuvi yaratiladi. Seeder xizmat kodi
 * bo'yicha idempotent.
 *
 * SLA bu yerda YARATILMAYDI: muddatlar endi zayavka kategoriyasida turadi
 * (categories.sla_accept_minutes / sla_work_minutes / sla_close_minutes).
 * Massivdagi `response` va `resolution` maydonlari manba hujjatning izi
 * sifatida qoldirildi.
 */
class ServiceCatalogSeeder extends Seeder
{
    /** @var list<array<string, string>> */
    private const CATALOG = [
        ['code' => 'ORG-001', 'name' => 'LCC Credit Conveyor Support', 'priority' => 'HIGH', 'response' => '1 Hour', 'resolution' => '5 days',
            'calendar' => '24x7', 'owner' => 'Axmadjanov Javlon', 'department' => 'Sevice Desk', 'description' => 'Resolution of technical issues arising during the submission of credit approval requests.'],
        ['code' => 'ORG-002', 'name' => 'Support of RCC Credit Conveyor', 'priority' => 'HIGH', 'response' => '1 Hour', 'resolution' => '5 days',
            'calendar' => '24x7', 'owner' => 'Axmadjanov Javlon', 'department' => 'Sevice Desk', 'description' => 'Resolution of technical issues occurring during the submission of credit approval requests.'],
        ['code' => 'ORG-003', 'name' => 'HRMS Support', 'priority' => 'HIGH', 'response' => '1 Hour', 'resolution' => '5 days',
            'calendar' => '24x7', 'owner' => 'Axmadjanov Javlon', 'department' => 'Sevice Desk', 'description' => 'Solving problems related to HRMS'],
        ['code' => 'ORG-004', 'name' => 'SEND ME\'\' Support', 'priority' => 'HIGH', 'response' => '1 Hour', 'resolution' => '5 days',
            'calendar' => '24x7', 'owner' => 'Axmadjanov Javlon', 'department' => 'Sevice Desk', 'description' => 'This type of request is submitted when detecting malfunctions or issues in the operation of the SEND ME system.'],
        ['code' => 'ORG-005', 'name' => 'Soft Collection', 'priority' => 'HIGH', 'response' => '1 Hour', 'resolution' => '5 days',
            'calendar' => '24x7', 'owner' => 'Axmadjanov Javlon', 'department' => 'Sevice Desk', 'description' => 'Creating report from data base ; Type of information: ; Date range of requested information: from **.**.**** to **.**.****: **. From **** **. **. ; Full description of required information: ; Department name: ; Phone number:'],
        ['code' => 'ORG-006', 'name' => 'Software installation', 'priority' => 'MEDIUM', 'response' => '30 minutes', 'resolution' => '8 hours',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'Installation of the following software: ; 1. E-Imzo plugin — 15 мин ; 2. Antivirus — 30 мин ; 3. Microsoft Office — 30 мин ; 4. Foxit Reader — 15 мин ; 5. WinRAR — 15 мин ; 6. Google Chrome, Mozilla Firefox —15 мин ; 7. Zoom —10 мин ; 8. De'],
        ['code' => 'ORG-007', 'name' => 'Software activation', 'priority' => 'MEDIUM', 'response' => '30 minutes', 'resolution' => '1 hour',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'Activation of Microsoft Office —15 мин'],
        ['code' => 'ORG-008', 'name' => 'Workplace setup for an employee', 'priority' => 'MEDIUM', 'response' => '30 minutes', 'resolution' => '8 hours',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'Installation of the following software: ; 1. E-Imzo plugin ; 2. Antivirus ; 3. Microsoft Office ; 4. Foxit Reader ; 5. WinRAR ; 6. Google Chrome, Mozilla Firefox ; 7. Zoom ; 8. Dev Agent ; 9. Ikey All, Stiyx Client, CNG Client, E-Pass ; 10.'],
        ['code' => 'ORG-009', 'name' => 'Zoom video conferencing', 'priority' => 'MEDIUM', 'response' => '15 minutes', 'resolution' => '1 hour',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'Creation and distribution of a video conference link, and assignment of a conference administrator. —10 мин'],
        ['code' => 'ORG-010', 'name' => 'Software Update', 'priority' => 'MEDIUM', 'response' => '15 minutes', 'resolution' => '1 hour',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'Update needed: ; 1) ; 2) ; 3) ; Phone: ; Department:'],
        ['code' => 'ORG-011', 'name' => 'Installing and Connecting a Printer', 'priority' => 'MEDIUM', 'response' => '15 minutes', 'resolution' => '1 hour',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'Printer repair needed: ; Scanner repair needed: ; Problem description: ; Phone: ; Department: ; Принтер улаш —15 мин'],
        ['code' => 'ORG-012', 'name' => 'Blocking and Unblocking a User', 'priority' => 'MEDIUM', 'response' => '15 minutes', 'resolution' => '1 hour',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'This type of case is applicable when an account is being blocked or unblocked —5 мин'],
        ['code' => 'ORG-013', 'name' => 'Repairing a printer or scanner', 'priority' => 'MEDIUM', 'response' => '1 week', 'resolution' => '30 days',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'This request is generated when a printer or scanner requires repair.'],
        ['code' => 'ORG-014', 'name' => 'Printers > Cartridge Replacement or Refilling', 'priority' => 'MEDIUM', 'response' => '1 hour', 'resolution' => '8 hours',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'This type of request is submitted when it is necessary to refill a cartridge.'],
        ['code' => 'ORG-015', 'name' => 'Computer Repair', 'priority' => 'MEDIUM', 'response' => '1 week', 'resolution' => '30 days',
            'calendar' => 'office', 'owner' => 'Abdullayev Mamurjon', 'department' => 'Sevice Desk', 'description' => 'This type of request is accepted when a computer needs repair.'],
        ['code' => 'CBS-001', 'name' => 'Technical support for iABS', 'priority' => 'CRITICAL', 'response' => '1 day', 'resolution' => '10 days',
            'calendar' => 'office', 'owner' => 'Tursunov Nasimjon/Safarov Sardor', 'department' => 'Support of the Core Banking system', 'description' => 'Problems addressed in collaboration with Fido Biznes'],
        ['code' => 'CBS-002', 'name' => 'iABS > System issues', 'priority' => 'CRITICAL', 'response' => '4 hours', 'resolution' => '3 days',
            'calendar' => 'office', 'owner' => 'Ilyosov Umid/Nabiev Fahriddin', 'department' => 'Support of the Core Banking system', 'description' => 'Resolving issues observed with individual clients\' accounts (merging process)'],
        ['code' => 'CBS-003', 'name' => 'Management of software updates and patches', 'priority' => 'HIGH', 'response' => '4 hours', 'resolution' => '7 days',
            'calendar' => 'office', 'owner' => 'Radjabov Sulton/Abduraimov Abdurashid', 'department' => 'Support of the Core Banking system', 'description' => 'Core Banking updates and patches'],
        ['code' => 'CBS-004', 'name' => 'iABS > Electronic Reports', 'priority' => 'MEDIUM', 'response' => '2 hours', 'resolution' => '7 days',
            'calendar' => 'office', 'owner' => 'Radjabov Sulton/Safarov Sardor', 'department' => 'Support of the Core Banking system', 'description' => 'This type of request is accepted for correcting errors that occur when generating a report through iABS.'],
        ['code' => 'CBS-005', 'name' => 'iABS > Retrieving Information', 'priority' => 'MEDIUM', 'response' => '2 hours', 'resolution' => '7 days',
            'calendar' => 'office', 'owner' => 'Tursunov Nasimjon/Ilyosov Umid', 'department' => 'Support of the Core Banking system', 'description' => 'Data export at the request of system users'],
        ['code' => 'DW-001', 'name' => 'Business Intelligence reports (BI)', 'priority' => 'MEDIUM', 'response' => '1 day', 'resolution' => '3 days',
            'calendar' => 'office', 'owner' => 'Tulaganov Ravshanbek', 'department' => 'Data Warehouse team', 'description' => 'Creation of custom reports upon request'],
        ['code' => 'DW-002', 'name' => 'BI Reports>Request permission to Dashboard', 'priority' => 'MEDIUM', 'response' => '1 day', 'resolution' => '3 days',
            'calendar' => 'office', 'owner' => 'Tulaganov Ravshanbek', 'department' => 'Data Warehouse team', 'description' => 'This type of request is used when applying for access to an existing dashboard.'],
        ['code' => 'INF-001', 'name' => 'Network Operations', 'priority' => 'HIGH', 'response' => '1 hour', 'resolution' => '3 days',
            'calendar' => 'office', 'owner' => 'Karabaev Khasan', 'department' => 'IT infrastructure', 'description' => 'Patch/Update Installation'],
        ['code' => 'INF-002', 'name' => 'Server Management', 'priority' => 'HIGH', 'response' => '1 hour', 'resolution' => '3 days',
            'calendar' => 'office', 'owner' => 'Raimkulov Farruh', 'department' => 'IT infrastructure', 'description' => 'Maintenance of virtual and physical servers (Network segment)'],
        ['code' => 'INF-003', 'name' => 'Backup and Recovery', 'priority' => 'HIGH', 'response' => '1 hour', 'resolution' => '3 days',
            'calendar' => 'office', 'owner' => 'Amonkeldiev Abdurahmon', 'department' => 'IT infrastructure', 'description' => 'Scheduled data backup, emergency recovery'],
    ];

    /** `services.criticality` — kichik son: 1 eng kritik. Excel'dagi Priority Level shunga o'giriladi. */
    private const CRITICALITY = ['CRITICAL' => 1, 'HIGH' => 2, 'MEDIUM' => 3, 'LOW' => 4];

    public function run(): void
    {
        foreach (DB::table('organizations')->whereNull('deleted_at')->pluck('id') as $organizationId) {
            foreach (self::CATALOG as $item) {
                $this->service((int) $organizationId, $item);
            }
        }
    }

    /** Xizmat va uning so'rov varianti (offering) — zayavka aynan shunga bog'lanadi. */
    private function service(int $organizationId, array $item): int
    {
        $serviceId = DB::table('services')->where('organization_id', $organizationId)
            ->where('code', $item['code'])->whereNull('deleted_at')->value('id');

        if (! $serviceId) {
            $serviceId = DB::table('services')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => $organizationId,
                'code' => $item['code'], 'name' => $item['name'], 'description' => $item['description'],
                'criticality' => self::CRITICALITY[$item['priority']] ?? 3, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now()]);
        }

        $hasOffering = DB::table('service_offerings')->where('organization_id', $organizationId)
            ->where('code', $item['code'])->whereNull('deleted_at')->exists();

        if (! $hasOffering) {
            DB::table('service_offerings')->insert(['public_id' => (string) Str::uuid(), 'organization_id' => $organizationId,
                'service_id' => $serviceId, 'code' => $item['code'], 'name' => $item['name'], 'description' => $item['description'],
                'is_requestable' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return (int) $serviceId;
    }
}
