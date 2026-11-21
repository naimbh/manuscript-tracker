<?php

namespace App\Support;

use App\Models\User;

/**
 * Starter list of IS, iSchool and HCI venues. Conferences are only those that
 * publish proceedings. Blank values are left for the AI update to fill from
 * official sources; nothing here is marked as checked.
 */
class VenueCatalog
{
    public static function conferences(): array
    {
        return [
            ['name' => 'CSCW (ACM Conference on Computer-Supported Cooperative Work)', 'publisher' => 'ACM', 'field' => 'HCI / CSCW',
                'proceedings' => 'PACM HCI (ACM Digital Library)', 'rank' => 'CORE A', 'acceptance_rate' => '~25–30%',
                'next_edition' => 'Oct 2027 (unconfirmed)', 'deadline_label' => 'Rolling submissions', 'website_url' => 'https://cscw.acm.org/'],
            ['name' => 'iConference', 'publisher' => 'iSchools', 'field' => 'Information science',
                'proceedings' => 'Springer LNCS', 'rank' => 'Main iSchool meeting',
                'next_edition' => '22–25 Mar 2027 virtual; 5–9 Apr 2027 Wellington, NZ', 'deadline_label' => 'Papers closed 15 Sep 2026',
                'website_url' => 'https://www.ischools.org/post/iconference-2027-call-for-papers'],
            ['name' => 'CHI (ACM Conference on Human Factors in Computing Systems)', 'publisher' => 'ACM', 'field' => 'HCI',
                'proceedings' => 'ACM Digital Library', 'rank' => 'CORE A*', 'acceptance_rate' => '~25%',
                'next_edition' => '10–14 May 2027, Pittsburgh', 'deadline_on' => '2027-01-21', 'deadline_label' => 'Posters (papers closed 10 Sep)',
                'website_url' => 'https://chi2027.acm.org/'],
            ['name' => 'AMCIS (Americas Conference on Information Systems)', 'publisher' => 'AIS', 'field' => 'Information systems',
                'proceedings' => 'AIS eLibrary', 'rank' => 'CORE2018 A',
                'next_edition' => '28–30 Jul 2027, São Paulo', 'deadline_on' => '2027-02-26', 'deadline_label' => 'Full papers (~late Feb)', 'deadline_expected' => true,
                'website_url' => 'https://aisnet.org/?p=5479'],
            ['name' => 'AOM Annual Meeting (OCIS division)', 'publisher' => 'Academy of Management', 'field' => 'Management / IS',
                'proceedings' => 'Academy of Management Proceedings', 'rank' => 'Top management meeting',
                'next_edition' => '30 Jul–3 Aug 2027, Vienna', 'deadline_on' => '2027-01-15', 'deadline_label' => 'Papers (~mid-Jan)', 'deadline_expected' => true,
                'website_url' => 'https://www.aom.org/news/aom-2027-annual-meeting-update/'],
            ['name' => 'ECSCW (European Conference on Computer-Supported Cooperative Work)', 'publisher' => 'EUSSET', 'field' => 'CSCW',
                'proceedings' => 'EUSSET Digital Library', 'rank' => 'CORE B',
                'next_edition' => '2027, Limassol, Cyprus', 'deadline_on' => '2027-03-01', 'deadline_label' => 'Papers (~early Mar)', 'deadline_expected' => true,
                'website_url' => 'https://ecscw.eusset.eu/'],
            ['name' => 'ASIS&T Annual Meeting', 'publisher' => 'ASIS&T', 'field' => 'Information science',
                'proceedings' => 'Proceedings of the ASIS&T (Wiley)', 'rank' => 'Main information-science meeting',
                'next_edition' => 'Nov 2027, Vancouver', 'deadline_on' => '2027-04-01', 'deadline_label' => 'Papers (~Apr)', 'deadline_expected' => true,
                'website_url' => 'https://www.asist.org/am/'],
            ['name' => 'ICIS (International Conference on Information Systems)', 'publisher' => 'AIS', 'field' => 'Information systems',
                'proceedings' => 'AIS eLibrary', 'rank' => 'CORE2018 A*', 'acceptance_rate' => '~25–30%',
                'next_edition' => '12–15 Dec 2027, Orlando', 'deadline_on' => '2027-05-01', 'deadline_label' => 'Full papers (~1 May)', 'deadline_expected' => true,
                'website_url' => 'https://aisnet.org/conferences/the-international-conference-on-information-systems/'],
            ['name' => 'IEEE eScience', 'publisher' => 'IEEE', 'field' => 'Research computing',
                'proceedings' => 'IEEE Xplore', 'rank' => 'CORE B',
                'deadline_on' => '2027-05-15', 'deadline_label' => 'Papers (~Apr–Jun)', 'deadline_expected' => true,
                'website_url' => 'https://www.escience-conference.org/'],
            ['name' => 'HAI (International Conference on Human-Agent Interaction)', 'publisher' => 'ACM', 'field' => 'HCI / AI',
                'proceedings' => 'ACM Digital Library', 'rank' => 'CORE B',
                'deadline_on' => '2027-06-01', 'deadline_label' => 'Papers (~late May/early Jun)', 'deadline_expected' => true,
                'website_url' => 'https://hai-conference.net/'],
            ['name' => 'HICSS (Hawaii International Conference on System Sciences)', 'publisher' => 'University of Hawaiʻi', 'field' => 'Information systems',
                'proceedings' => 'ScholarSpace (open access)', 'rank' => 'CORE2018 A', 'acceptance_rate' => '~47%',
                'next_edition' => 'HICSS-61, Jan 2028, Hawaii', 'deadline_on' => '2027-06-15', 'deadline_label' => 'Full papers (~15 Jun)', 'deadline_expected' => true,
                'website_url' => 'https://hicss.hawaii.edu/'],
            ['name' => 'ACM GROUP', 'publisher' => 'ACM', 'field' => 'CSCW',
                'proceedings' => 'PACM HCI (ACM Digital Library)', 'rank' => 'CORE B', 'acceptance_rate' => '~30%',
                'deadline_label' => 'Every two years; next call ~mid-2027 for GROUP 2029',
                'website_url' => 'https://group.acm.org/conferences/group27/papers.php'],
            ['name' => 'ECIS (European Conference on Information Systems)', 'publisher' => 'AIS', 'field' => 'Information systems',
                'proceedings' => 'AIS eLibrary', 'rank' => 'CORE2018 A',
                'next_edition' => '24–26 May 2027, Luxembourg', 'deadline_label' => 'Full papers closed 30 Oct 2026',
                'website_url' => 'https://ecis2027.uni.lu/'],
            ['name' => 'VL/HCC (IEEE Symposium on Visual Languages and Human-Centric Computing)', 'publisher' => 'IEEE', 'field' => 'HCI',
                'proceedings' => 'IEEE Xplore', 'website_url' => 'https://conf.researchr.org/home/vlhcc-2027'],
            // Further venues with proceedings. Dates are left for the AI update.
            ['name' => 'PACIS (Pacific Asia Conference on Information Systems)', 'publisher' => 'AIS', 'field' => 'Information systems', 'proceedings' => 'AIS eLibrary'],
            ['name' => 'ACIS (Australasian Conference on Information Systems)', 'publisher' => 'AIS', 'field' => 'Information systems', 'proceedings' => 'AIS eLibrary'],
            ['name' => 'ICWSM (International AAAI Conference on Web and Social Media)', 'publisher' => 'AAAI', 'field' => 'Social computing', 'proceedings' => 'AAAI Digital Library', 'website_url' => 'https://www.icwsm.org/'],
            ['name' => 'DIS (ACM Designing Interactive Systems)', 'publisher' => 'ACM', 'field' => 'HCI / design', 'proceedings' => 'ACM Digital Library', 'website_url' => 'https://dis.acm.org/'],
            ['name' => 'ACM FAccT (Fairness, Accountability, and Transparency)', 'publisher' => 'ACM', 'field' => 'AI and society', 'proceedings' => 'ACM Digital Library', 'website_url' => 'https://facctconference.org/'],
            ['name' => 'AIES (AAAI/ACM Conference on AI, Ethics, and Society)', 'publisher' => 'AAAI / ACM', 'field' => 'AI and society', 'proceedings' => 'AAAI Digital Library'],
            ['name' => 'ACM CHIIR (Human Information Interaction and Retrieval)', 'publisher' => 'ACM', 'field' => 'Information science', 'proceedings' => 'ACM Digital Library'],
            ['name' => 'JCDL (ACM/IEEE Joint Conference on Digital Libraries)', 'publisher' => 'ACM / IEEE', 'field' => 'Information science', 'proceedings' => 'ACM Digital Library'],
            ['name' => 'ACM COMPASS (Computing and Sustainable Societies)', 'publisher' => 'ACM', 'field' => 'ICT4D', 'proceedings' => 'ACM Digital Library'],
            ['name' => 'IFIP WG 9.4 Conference (Social Implications of Computers in Developing Countries)', 'publisher' => 'IFIP', 'field' => 'ICT4D', 'proceedings' => 'Springer IFIP AICT'],
        ];
    }

    public static function journals(): array
    {
        $j = fn (string $name, string $publisher, string $field, ?string $q = null) => compact('name', 'publisher', 'field') + ['quartile' => $q];

        return [
            // Information systems
            $j('MIS Quarterly', 'MISRC, University of Minnesota', 'Information systems', 'Q1'),
            $j('Information Systems Research', 'INFORMS', 'Information systems', 'Q1'),
            $j('Journal of the Association for Information Systems', 'AIS', 'Information systems', 'Q1'),
            $j('European Journal of Information Systems', 'Taylor & Francis', 'Information systems', 'Q1'),
            $j('Information Systems Journal', 'Wiley', 'Information systems', 'Q1'),
            $j('Journal of Information Technology', 'SAGE', 'Information systems', 'Q1'),
            $j('Journal of Management Information Systems', 'Taylor & Francis', 'Information systems', 'Q1'),
            $j('Journal of Strategic Information Systems', 'Elsevier', 'Information systems', 'Q1'),
            $j('Information and Organization', 'Elsevier', 'Information systems', 'Q1'),
            $j('Information & Management', 'Elsevier', 'Information systems', 'Q1'),
            $j('International Journal of Information Management', 'Elsevier', 'Information systems', 'Q1'),
            $j('Information Technology & People', 'Emerald', 'Information systems'),
            $j('Communications of the Association for Information Systems', 'AIS', 'Information systems'),
            // ICT4D and development
            $j('Information Technology for Development', 'Taylor & Francis', 'ICT4D'),
            $j('The Electronic Journal of Information Systems in Developing Countries', 'Wiley', 'ICT4D'),
            $j('Third World Quarterly', 'Taylor & Francis', 'Development studies'),
            // Information science
            $j('Journal of the Association for Information Science and Technology', 'Wiley', 'Information science', 'Q1'),
            $j('The Information Society', 'Taylor & Francis', 'Information science'),
            $j('Journal of Documentation', 'Emerald', 'Information science'),
            // HCI and CSCW
            $j('Proceedings of the ACM on Human-Computer Interaction (PACM HCI)', 'ACM', 'HCI / CSCW'),
            $j('Computer Supported Cooperative Work (CSCW)', 'Springer', 'CSCW'),
            $j('ACM Transactions on Computer-Human Interaction', 'ACM', 'HCI', 'Q1'),
            $j('Human–Computer Interaction', 'Taylor & Francis', 'HCI'),
            $j('ACM AI Letters', 'ACM', 'AI'),
            // Work, media and society
            $j('New Technology, Work and Employment', 'Wiley', 'Sociology of work'),
            $j('Work, Employment and Society', 'SAGE', 'Sociology of work'),
            $j('New Media & Society', 'SAGE', 'Media and communication'),
            $j('Information, Communication & Society', 'Taylor & Francis', 'Media and communication'),
            $j('Big Data & Society', 'SAGE', 'Media and communication'),
        ];
    }

    /** Add any catalogue venues the user does not have yet. Returns how many were added. */
    public static function seedFor(User $user): int
    {
        $have = $user->venues()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $n = 0;
        foreach ([['conference', self::conferences()], ['journal', self::journals()]] as [$kind, $rows]) {
            foreach ($rows as $row) {
                if (in_array(mb_strtolower($row['name']), $have, true)) {
                    continue;
                }
                $user->venues()->create($row + ['kind' => $kind, 'watch' => $kind === 'conference']);
                $n++;
            }
        }

        return $n;
    }
}
