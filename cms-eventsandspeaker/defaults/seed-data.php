<?php
/**
 * Feste Seed-Daten für 365NET Events & Speaker.
 *
 * Die CSV-Struktur ist bewusst direkt im Plugin hinterlegt. Es gibt keine
 * Upload- oder Import-Funktion; die Daten dienen als Default-Werte bei der
 * Aktivierung/Migration und werden anschließend über Admin-CRUD gepflegt.
 *
 * @package CMS_365NETEvents
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$csv = <<<'CSV'
NR;EVENT NAME;WANN;BIS WANN;ORT;VERANSTALTER;Spalte1;SPEAKER NR;VORNAME;NACHNAME;FIRMA;THEMA/KATEGORIE;MVP/AUSZEICHNUNG;WEBSITE;EVENT ART;PREIS
1;Net-Zero Data Centre Summit;14.01.2026;15.01.2026;Berlin;Net Zero Compare;;1;;;Future Bridge;Datacenter-Nachhaltigkeit;;https://netzerocompare.com/;;
2;OmniSecure 2026;19.01.2026;21.01.2026;Berlin;OmniSecure;;1;Heike;Hagemeier;BMI;Cybersecurity Research;;https://www.omnisecure.berlin/;;
2;OmniSecure 2026;19.01.2026;21.01.2026;Berlin;OmniSecure;;2;Daniel;Loebenberger;OTH Amberg-Weiden;Informatik / Kryptographie;;https://www.omnisecure.berlin/;;
2;OmniSecure 2026;19.01.2026;21.01.2026;Berlin;OmniSecure;;3;Marian;Margraf;Freie Universität Berlin;Informatik / Digital ID;;https://www.omnisecure.berlin/;;
2;OmniSecure 2026;19.01.2026;21.01.2026;Berlin;OmniSecure;;4;Stephan;Klein;Governikus;Digital Identity / eID;;https://www.governikus.de/;;
2;OmniSecure 2026;19.01.2026;21.01.2026;Berlin;OmniSecure;;5;Hartje;Bruns;Governikus;Digital Identity;;https://www.governikus.de/;;
2;OmniSecure 2026;19.01.2026;21.01.2026;Berlin;OmniSecure;;6;Tobias;Fehenberger;Adva Network Security;Quantum-Safe Cryptography;;https://www.omnisecure.berlin/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;1;Marwan;Abu-Khalil;Siemens AG;Software Architecture;;https://www.oop-konferenz.de/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;2;Adam;Bien;adam-bien.com;Java / Cloud Native;;http://adam-bien.com/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;3;Stefan;Toth;embarc GmbH;Software-Architektur;;https://www.oop-konferenz.de/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;4;Falk;Sippach;embarc GmbH;Software-Architektur / DDD;;https://www.oop-konferenz.de/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;5;Stefan;Zörner;embarc GmbH;Software-Architektur;;https://www.oop-konferenz.de/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;6;Kim;Duggen;embarc GmbH;Software-Architektur;;https://www.oop-konferenz.de/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;7;Alexander;Kaserbacher;;Software Architecture;;https://www.oop-konferenz.de/;;
3;OOP 2026 – Software-Architektur;10.02.2026;13.02.2026;München (MOC);SIGS DATACOM;;8;Felix;Kammerlander;;Software Architecture;;https://www.oop-konferenz.de/;;
4;Hamburger IT-Strategietage;18.02.2026;20.02.2026;Hamburg;Handelsblatt / EUROFORUM;;1;Bernd;Rattey;Deutsche Bahn;CIO / IT-Strategie;;https://www.it-strategietage.de/;;
4;Hamburger IT-Strategietage;18.02.2026;20.02.2026;Hamburg;Handelsblatt / EUROFORUM;;2;Karsten;Wildberger;BM Digitales;Digitalpolitik;;https://www.it-strategietage.de/;;
4;Hamburger IT-Strategietage;18.02.2026;20.02.2026;Hamburg;Handelsblatt / EUROFORUM;;3;Elke;Anderl;T-Systems;Industrial AI Cloud;;https://www.t-systems.com/;;
4;Hamburger IT-Strategietage;18.02.2026;20.02.2026;Hamburg;Handelsblatt / EUROFORUM;;4;Claudia;Plattner;BSI;Cybersecurity & AI;;https://www.bsi.bund.de/;;
4;Hamburger IT-Strategietage;18.02.2026;20.02.2026;Hamburg;Handelsblatt / EUROFORUM;;5;Franz;Decker;BMW;CIO / Digital Transformation;;https://www.it-strategietage.de/;;
5;Power Platform Bootcamp;20.02.2026;21.02.2026;Diverse / Virtuell;Community Days;;1;;;Microsoft MVPs;Power Apps / Power Automate / Power BI;MVP;https://www.communitydays.org/;;
6;Pottconf 2026;23.02.2026;24.02.2026;Essen;Community;;1;Thorsten;Butz;IT-Trainer;M365 / Azure / Modern Work;Microsoft MVP;https://www.communitydays.org/;;
7;Exchange Summit 2026;24.02.2026;25.02.2026;Deutschland;Community;;1;;;Exchange MVPs;Exchange Server / Online / Hybrid;MVP;https://www.communitydays.org/;;
8;SQLKonferenz 2026;02.03.2026;04.03.2026;Hanau;Community;;1;;;SQL Server MVPs;SQL Server / Azure SQL / PostgreSQL;MVP;https://www.communitydays.org/;;
9;IT-Trans 2026;03.03.2026;05.03.2026;Karlsruhe;Karlsruher Messe;;1;Anna-Theresa;Korbutt;Hamburg Transport (HHA);ÖPNV / Keynote;;https://www.it-trans.org/;;
9;IT-Trans 2026;03.03.2026;05.03.2026;Karlsruhe;Karlsruher Messe;;2;Frank;Mentrup;Stadt Karlsruhe;Oberbürgermeister / Patronage;;https://www.it-trans.org/;;
9;IT-Trans 2026;03.03.2026;05.03.2026;Karlsruhe;Karlsruher Messe;;3;Martin;Kagerbauer;KIT;Verkehrsplanung;;https://www.kit.edu/;;
9;IT-Trans 2026;03.03.2026;05.03.2026;Karlsruhe;Karlsruher Messe;;4;Elke;Zimmer;MdL Baden-Württemberg;Staatssekretärin Verkehr;;https://www.it-trans.org/;;
9;IT-Trans 2026;03.03.2026;05.03.2026;Karlsruhe;Karlsruher Messe;;5;Alexander;Pischon;KVV;ÖPNV-Management;;https://www.it-trans.org/;;
9;IT-Trans 2026;03.03.2026;05.03.2026;Karlsruhe;Karlsruher Messe;;6;Natalie;Rodriguez;Hamburger Hochbahn;Innovation & Strategie;;https://www.it-trans.org/;;
10;Light + Building 2026;08.03.2026;13.03.2026;Frankfurt;Messe Frankfurt;;1;;;Internationale Experten;Smart Building / IoT / Gebäudeautomation;;https://light-building.messefrankfurt.com/;;
11;JavaLand 2026;10.03.2026;12.03.2026;Brühl (Phantasialand);iJUG e.V.;;1;Venkat;Subramaniam;Agile Developer Inc.;Java / Agile Development;Java Champion;https://www.javaland.eu/;;
11;JavaLand 2026;10.03.2026;12.03.2026;Brühl (Phantasialand);iJUG e.V.;;2;Patrick;Baumgartner;;Java Development;;https://www.javaland.eu/;;
11;JavaLand 2026;10.03.2026;12.03.2026;Brühl (Phantasialand);iJUG e.V.;;3;Adam;Bien;adam-bien.com;Java / Cloud Native;Java Champion;http://adam-bien.com/;;
11;JavaLand 2026;10.03.2026;12.03.2026;Brühl (Phantasialand);iJUG e.V.;;4;Bruno;Borges;;Java Development;;https://www.javaland.eu/;;
12;embedded world 2026;10.03.2026;12.03.2026;Nürnberg;NürnbergMesse;;1;Richard J.;Simoncic;Microchip Technology;IoT / AI Keynote;;https://www.embedded-world.de/;;
12;embedded world 2026;10.03.2026;12.03.2026;Nürnberg;NürnbergMesse;;2;Stefan;Finkbeiner;Bosch Sensortec;Sensor Technology / IoT;;https://www.embedded-world.de/;;
12;embedded world 2026;10.03.2026;12.03.2026;Nürnberg;NürnbergMesse;;3;Daniel;Müller-Gritschneder;TU Wien;Embedded Systems;;https://www.embedded-world.de/;;
12;embedded world 2026;10.03.2026;12.03.2026;Nürnberg;NürnbergMesse;;4;Axel;Sikora;HS Offenburg;Embedded Systems;;https://www.embedded-world.de/;;
12;embedded world 2026;10.03.2026;12.03.2026;Nürnberg;NürnbergMesse;;5;Easen;Ho;Vantron Technology;Embedded / IoT;;https://www.embedded-world.de/;;
13;Rethink! Cloud & Infrastructure Security;11.03.2026;13.03.2026;Berlin;we.CONECT;;1;Gregor;Kuznik;;Global Information Security Officer / Zero Trust;;https://www.we-conect.com/;;
13;Rethink! Cloud & Infrastructure Security;11.03.2026;13.03.2026;Berlin;we.CONECT;;2;Klaus;Bauer;;Head of Research / Cloud Security;;https://www.we-conect.com/;;
13;Rethink! Cloud & Infrastructure Security;11.03.2026;13.03.2026;Berlin;we.CONECT;;3;Klaus;Klingner;;Information Security Officer;;https://www.we-conect.com/;;
13;Rethink! Cloud & Infrastructure Security;11.03.2026;13.03.2026;Berlin;we.CONECT;;4;Louis;Servin;;Enterprise Security Architecture;;https://www.we-conect.com/;;
13;Rethink! Cloud & Infrastructure Security;11.03.2026;13.03.2026;Berlin;we.CONECT;;5;Pascal;Reiniger;;CISO / Ransomware Defense;;https://www.we-conect.com/;;
14;Troopers 2026;22.06.2026;26.06.2026;Heidelberg;ERNW;;1;;;ERNW / Security Community;Deep Security Research / Pentesting;;https://troopers.de/;;
15;secIT 2026;17.03.2026;19.03.2026;Hannover;heise medien;;1;Pierre-Alain;Mouy;;Cybersecurity in Space / Quantum;;https://www.heise.de/secit;;
15;secIT 2026;17.03.2026;19.03.2026;Hannover;heise medien;;2;Moritz;Mayer;A1 Digital;IT Security Solutions;;https://www.heise.de/secit;;
16;Bitkom TRANSFORM 2026;18.03.2026;19.03.2026;Berlin;Bitkom;;1;Ralf;Wintergerst;Giesecke+Devrient / Bitkom;Digital Transformation / Bitkom-Präsident;;https://www.bitkom.org/;;
16;Bitkom TRANSFORM 2026;18.03.2026;19.03.2026;Berlin;Bitkom;;2;Bärbel;Bas;Bundesregierung;BM Arbeit & Soziales;;https://www.bitkom.org/;;
16;Bitkom TRANSFORM 2026;18.03.2026;19.03.2026;Berlin;Bitkom;;3;Carsten;Breuer;Bundeswehr;Generalinspekteur;;https://www.bitkom.org/;;
16;Bitkom TRANSFORM 2026;18.03.2026;19.03.2026;Berlin;Bitkom;;4;Claudia;Plattner;BSI;Cybersecurity;;https://www.bsi.bund.de/;;
17;ESE Kongress 2026;02.12.2026;04.12.2026;Sindelfingen;Vogel / Elektronikpraxis;;1;;;Embedded-Entwickler;Embedded Software Engineering / RTOS / Safety;;https://www.ese-kongress.de/;;
18;German Testing Day 2026;06.05.2026;07.05.2026;Frankfurt;German Testing Board;;1;;;Testing-Experten DACH;Testautomatisierung / Agile Testing / QA;;https://www.germantestingday.info/;;
19;CloudFest 2026;23.03.2026;26.03.2026;Europa-Park, Rust;WHD Event GmbH;;1;Brittany;Kaiser;Own Your Data;Digital Rights / Data Privacy;;https://www.cloudfest.com/;;
19;CloudFest 2026;23.03.2026;26.03.2026;Europa-Park, Rust;WHD Event GmbH;;2;Sebastian;Schreiber;SySS GmbH;Cybersecurity / Penetration Testing;;https://www.syss.de/;;
19;CloudFest 2026;23.03.2026;26.03.2026;Europa-Park, Rust;WHD Event GmbH;;3;Radia;Perlman;Internet Pioneer;Internet Infrastructure / Networking;;https://www.cloudfest.com/;;
20;KommDigitale 2026;25.03.2026;26.03.2026;Bielefeld;Databund / KOMM GROUP;;1;Bernd;Schlömer;CIO Sachsen-Anhalt;Digitale Verwaltung;;https://kommdigitale.de/;;
20;KommDigitale 2026;25.03.2026;26.03.2026;Bielefeld;Databund / KOMM GROUP;;2;Alexander;Handschuh;DStGB;Kommunale Digitalisierung;;https://kommdigitale.de/;;
20;KommDigitale 2026;25.03.2026;26.03.2026;Bielefeld;Databund / KOMM GROUP;;3;Ludmilla;Middecke;Stadt Bielefeld;eGovernment-Strategie;;https://kommdigitale.de/;;
20;KommDigitale 2026;25.03.2026;26.03.2026;Bielefeld;Databund / KOMM GROUP;;4;Detlef;Bäumer;PICTURE GmbH;Prozessmanagement / Change;;https://kommdigitale.de/;;
20;KommDigitale 2026;25.03.2026;26.03.2026;Bielefeld;Databund / KOMM GROUP;;5;Oliver;Kreth;Ceyoniq;Digital Transformation / DMS;;https://www.ceyoniq.com/;;
20;KommDigitale 2026;25.03.2026;26.03.2026;Bielefeld;Databund / KOMM GROUP;;6;Robert;Wander;E-Government Consulting;Verwaltungsdigitalisierung;;https://kommdigitale.de/;;
21;AI Conference: Science x Business;14.04.2026;15.04.2026;Heidelberg;AI Conference;;1;Matthias;Blatz;Heidelberg iT Management;AI / Data Center;;https://www.ai-conference.de/;;
21;AI Conference: Science x Business;14.04.2026;15.04.2026;Heidelberg;AI Conference;;2;Eckart;Würzner;Stadt Heidelberg;OB / AI Policy;;https://www.ai-conference.de/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;1;Dona;Sarkar;Microsoft;Microsoft Copilot;Microsoft;https://www.communitydays.org/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;2;Kevin;McDonnell;;Microsoft Technologies;MVP;https://www.communitydays.org/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;3;Zoe;Wilson;;Microsoft Technologies;MVP;https://www.communitydays.org/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;4;Sara;Fennah;;Microsoft Governance;MVP;https://www.communitydays.org/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;5;Raphael;Köllner;;Microsoft Technologies;MVP;https://www.communitydays.org/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;6;Vesa;Nopanen;;Microsoft Technologies;MVP;https://www.communitydays.org/;;
22;Copilot Community Conference Europe;14.04.2026;14.04.2026;Virtuell (DE);Community Days;;7;Pieter;Op de Beéck;;Microsoft Technologies;MVP;https://www.communitydays.org/;;
23;BSI IT-Sicherheitskongress;15.04.2026;16.04.2026;Bonn;BSI;;1;Claudia;Plattner;BSI;IT-Sicherheit / Cyberresilienz;BSI-Präsidentin;https://www.bsi.bund.de/;;
24;ColorCloud 2026;15.04.2026;17.04.2026;Hamburg;Community Days;;1;Keith;Atherton;;Power Platform Architecture;MVP;https://www.communitydays.org/;;
24;ColorCloud 2026;15.04.2026;17.04.2026;Hamburg;Community Days;;2;Scott;Durow;Microsoft;Power Platform / Vibe Engineering;;https://www.communitydays.org/;;
24;ColorCloud 2026;15.04.2026;17.04.2026;Hamburg;Community Days;;3;Marthe;Moengen;;Microsoft Fabric / Power BI;;https://www.communitydays.org/;;
24;ColorCloud 2026;15.04.2026;17.04.2026;Hamburg;Community Days;;4;Emilie;Rønning;;Microsoft Fabric / Data;;https://www.communitydays.org/;;
24;ColorCloud 2026;15.04.2026;17.04.2026;Hamburg;Community Days;;5;Kathrin;Borchert;;Power BI & Fabric Administration;MVP;https://www.communitydays.org/;;
25;Hannover Messe 2026;20.04.2026;24.04.2026;Hannover;Deutsche Messe AG;;1;Cedrik;Neike;Siemens AG;Industrial Automation / AI;;https://www.siemens.com/;;
25;Hannover Messe 2026;20.04.2026;24.04.2026;Hannover;Deutsche Messe AG;;2;Armin;Papperger;Rheinmetall;Defense & Technology;;https://www.hannovermesse.de/;;
25;Hannover Messe 2026;20.04.2026;24.04.2026;Hannover;Deutsche Messe AG;;3;Amy;Webb;Future Today Institute;Technology Trends / AI;;https://amywebb.io/;;
25;Hannover Messe 2026;20.04.2026;24.04.2026;Hannover;Deutsche Messe AG;;4;Julie;Sweet;Accenture;Organizational Transformation / AI;;https://www.accenture.com/;;
25;Hannover Messe 2026;20.04.2026;24.04.2026;Hannover;Deutsche Messe AG;;5;Pablo;Erat;On AG / LightSpray;Industrial Technology;;https://www.hannovermesse.de/;;
26;PostgreSQL Conference Germany;21.04.2026;22.04.2026;Essen;PostgreSQL Europe;;1;Emma;Saroyan;;Open Source Community Building;;https://2026.pgconf.de/;;
26;PostgreSQL Conference Germany;21.04.2026;22.04.2026;Essen;PostgreSQL Europe;;2;Valeria;Kaplan;;PostgreSQL Contributions;;https://2026.pgconf.de/;;
26;PostgreSQL Conference Germany;21.04.2026;22.04.2026;Essen;PostgreSQL Europe;;3;Jan;Wieremjewicz;;PostgreSQL / Legacy Systems;;https://2026.pgconf.de/;;
26;PostgreSQL Conference Germany;21.04.2026;22.04.2026;Essen;PostgreSQL Europe;;4;Chris;Engelbert;;PostgreSQL / Async I/O;;https://2026.pgconf.de/;;
26;PostgreSQL Conference Germany;21.04.2026;22.04.2026;Essen;PostgreSQL Europe;;5;Dirk;Krautschick;;Database Performance;;https://2026.pgconf.de/;;
27;DMEA 2026 – Digital Health;21.04.2026;23.04.2026;Berlin;bvitg / Messe Berlin;;1;Nina;Warken;BM Gesundheit;Healthcare Policy / Digital Health;Bundesministerin;https://www.dmea.de/;;
27;DMEA 2026 – Digital Health;21.04.2026;23.04.2026;Berlin;bvitg / Messe Berlin;;2;David;Matusiewicz;FOM University;Healthcare Management / Digital Health;;https://www.dmea.de/;;
27;DMEA 2026 – Digital Health;21.04.2026;23.04.2026;Berlin;bvitg / Messe Berlin;;3;Kristina;Sinemus;;Digital Infrastructure;;https://www.dmea.de/;;
27;DMEA 2026 – Digital Health;21.04.2026;23.04.2026;Berlin;bvitg / Messe Berlin;;4;Louisa;Specht-Riemenschneider;;Digital Health / Datenschutz;;https://www.dmea.de/;;
27;DMEA 2026 – Digital Health;21.04.2026;23.04.2026;Berlin;bvitg / Messe Berlin;;5;Nina;Ruge;Moderation;Media / Health Communication;;https://www.dmea.de/;;
28;Cybersecurity Summit Hamburg;28.04.2026;29.04.2026;Hamburg;Cybersecurity Summit;;1;Noël;Funke;Hackers4Good;Cybersecurity / Ethical Hacking;;https://cybersecuritysumm.it/;;
28;Cybersecurity Summit Hamburg;28.04.2026;29.04.2026;Hamburg;Cybersecurity Summit;;2;Chris;Müller;Ratepay;Cybersecurity / FinTech;;https://cybersecuritysumm.it/;;
28;Cybersecurity Summit Hamburg;28.04.2026;29.04.2026;Hamburg;Cybersecurity Summit;;3;Sebastian;Schreiber;SySS GmbH;Penetration Testing;;https://www.syss.de/;;
28;Cybersecurity Summit Hamburg;28.04.2026;29.04.2026;Hamburg;Cybersecurity Summit;;4;Dennis Kim;Schmidt;Dataport AöR;Public Sector IT Security;;https://cybersecuritysumm.it/;;
28;Cybersecurity Summit Hamburg;28.04.2026;29.04.2026;Hamburg;Cybersecurity Summit;;5;Markus;Engelke;Munich Re;Enterprise Cybersecurity;;https://cybersecuritysumm.it/;;
29;JAX 2026;04.05.2026;08.05.2026;Mainz;Software & Support Media;;1;;;Java Champions / Software-Architekten;Java / Cloud / Microservices / DevOps / AI;;https://jax.de/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;1;Tom;Brady;;Business / Entrepreneurship;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;2;Scott;Galloway;NYU Stern;Business Analysis / Tech;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;3;Christian;Sewing;Deutsche Bank;Finance / Banking;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;4;Karsten;Wildberger;BM Digitales;Digitalpolitik;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;5;Meredith;Whittaker;Signal;Tech / AI Policy;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;6;Will;Ahmed;Whoop;Sports Technology;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;7;Bettina;Orlopp;Commerzbank;Finance / Banking;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;8;Grant;LaFontaine;Whatnot;Live Shopping / E-Commerce;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;9;Leonardo;Aizpuru;Nespresso;Marketing / Brand;;https://omr.com/;;
30;OMR Festival 2026;05.05.2026;06.05.2026;Hamburg;OMR;;10;Laura;Nestler;;Community Building;;https://omr.com/;;
31;European Collaboration Summit;05.05.2026;07.05.2026;Köln;CollabSummit;;1;Nikki;Chapple;;M365 / Governance;Microsoft MVP;https://collabsummit.eu/;;
31;European Collaboration Summit;05.05.2026;07.05.2026;Köln;CollabSummit;;2;Sara;Fennah;;M365 / Governance / Copilot;Microsoft MVP;https://collabsummit.eu/;;
31;European Collaboration Summit;05.05.2026;07.05.2026;Köln;CollabSummit;;3;Mark;Rackley;;M365 / Copilot;Microsoft MVP;https://collabsummit.eu/;;
31;European Collaboration Summit;05.05.2026;07.05.2026;Köln;CollabSummit;;4;Andrew;Connell;;M365 / Copilot;Microsoft MVP;https://collabsummit.eu/;;
32;Rise of AI Conference;05.05.2026;06.05.2026;Berlin;Rise of AI;;1;Feiyu;Xu;Amber Iris AI / Uni Digital Science;AI Research / Industry;;https://riseof.ai/;;
32;Rise of AI Conference;05.05.2026;06.05.2026;Berlin;Rise of AI;;2;Hans;Uszkoreit;;AI Research / NLP;;https://riseof.ai/;;
32;Rise of AI Conference;05.05.2026;06.05.2026;Berlin;Rise of AI;;3;Tina;Klüwer;;Conversational AI;;https://riseof.ai/;;
32;Rise of AI Conference;05.05.2026;06.05.2026;Berlin;Rise of AI;;4;Antonio;Krüger;DFKI;AI Research / HCI;;https://www.dfki.de/;;
32;Rise of AI Conference;05.05.2026;06.05.2026;Berlin;Rise of AI;;5;Rasmus;Rothe;Merantix AG;AI Startups / Deep Learning;;https://riseof.ai/;;
32;Rise of AI Conference;05.05.2026;06.05.2026;Berlin;Rise of AI;;6;Jürgen;Schmidhuber;KAUST / NNAISENSE;AI Research / Neural Networks;;https://riseof.ai/;;
33;Tech Show Frankfurt;06.05.2026;07.05.2026;Frankfurt;CloserStill Media;;1;Marcus;Schuler;F.A.Z. / implicator.ai;Technology Journalism;;https://www.techshowfrankfurt.de/;;
33;Tech Show Frankfurt;06.05.2026;07.05.2026;Frankfurt;CloserStill Media;;2;Petya;Dasheva;;Data Strategy / Human-Centered Analytics;;https://www.techshowfrankfurt.de/;;
33;Tech Show Frankfurt;06.05.2026;07.05.2026;Frankfurt;CloserStill Media;;3;Carsten;Bange;BARC;Data Analytics / BI / AI;;https://www.techshowfrankfurt.de/;;
33;Tech Show Frankfurt;06.05.2026;07.05.2026;Frankfurt;CloserStill Media;;4;Miriam;Meckel;;Communication / Technology;;https://www.techshowfrankfurt.de/;;
34;MSP Konferenz 2026;06.05.2026;07.05.2026;Mainz;MSP Konferenz;;1;;;MSP-Experten;Managed Service Provider / Tools / Security;;https://msp-konferenz.de/;;
35;Cloud Native Conference;13.05.2026;13.05.2026;Frankfurt;Cloud Native Conf;;1;;;Cloud-Native-Experten;Kubernetes / Containers / Serverless;;https://www.cloudnativeconference.de/;;
36;SERVIEW Summit (SERVIEW26);19.05.2026;21.05.2026;Seeheim;SERVIEW GmbH;;1;;;ITSM/ITIL-Keynotes;ITIL 4 / DORA / NIS-2 / AI Act;;https://www.serview-summit.com/;;
37;DACH InfoSec Network;19.05.2026;22.05.2026;Deutschland;DACH InfoSec;;1;;;CISOs großer DACH-Unternehmen;Cloud Security / Digital ID / Governance;;https://www.communitydays.org/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;1;Lina;Böcker;;Datenschutz / IT-Recht / AI-Regulierung;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;2;Dagmar;Hartge;LfDI Brandenburg;Datenschutzpolitik;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;3;Thomas;Hoeren;Uni Münster;IT-Recht / Informationssicherheit;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;4;Dieter;Kugelmann;LfDI Rheinland-Pfalz;Datenschutzregulierung;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;5;Philip;Radlanski;Greenberg Traurig;AI / Datenschutz / Cybersecurity;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;6;Thomas;Fuchs;HmbBfDI;Datenschutzpolitik;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;7;Meike;Kamp;BlnBDI;Datenschutzpolitik;;https://www.datenschutzkongress.de/;;
38;Datenschutzkongress 2026;19.05.2026;20.05.2026;Berlin;EUROFORUM / Handelsblatt;;8;Philipp;Räther;Allianz Group;Privacy / AI Trust Officer;;https://www.datenschutzkongress.de/;;
39;ALSO Channel Trends & Visions;29.05.2026;29.05.2026;Köln;ALSO Deutschland;;1;Yasmin;Weiß;;KI / Workplace Transformation;;https://www.also.de/;;
39;ALSO Channel Trends & Visions;29.05.2026;29.05.2026;Köln;ALSO Deutschland;;2;Stefan;Blome;ALSO;CCO / IT-Channel;;https://www.also.de/;;
40;TECH by Handelsblatt;31.05.2026;01.06.2026;Heilbronn;Handelsblatt;;1;Hermann Ludwig;Moeller;ESPI;Science / Policy;;https://live.handelsblatt.com/;;
41;ISX IT-Security Conference Frankfurt;03.06.2026;03.06.2026;Frankfurt;Vogel IT-Akademie;;1;Thomas;Hemker;;Adversary Analysis;;https://www.security-insider.de/;;
41;ISX IT-Security Conference Frankfurt;03.06.2026;03.06.2026;Frankfurt;Vogel IT-Akademie;;2;Kerstin;Zettl-Schabath;;Adversary Analysis;;https://www.security-insider.de/;;
41;ISX IT-Security Conference Frankfurt;03.06.2026;03.06.2026;Frankfurt;Vogel IT-Akademie;;3;Swantje;Westpfahl;;Cyber Risks / Geopolitik;;https://www.security-insider.de/;;
42;ISX IT-Security Conference München;09.06.2026;09.06.2026;München;Vogel IT-Akademie;;1;Thomas;Hemker;;Adversary Analysis;;https://www.security-insider.de/;;
42;ISX IT-Security Conference München;09.06.2026;09.06.2026;München;Vogel IT-Akademie;;2;Kerstin;Zettl-Schabath;;Adversary Analysis;;https://www.security-insider.de/;;
42;ISX IT-Security Conference München;09.06.2026;09.06.2026;München;Vogel IT-Akademie;;3;Swantje;Westpfahl;;Cyber Risks / Geopolitik;;https://www.security-insider.de/;;
43;DATA festival München;16.06.2026;17.06.2026;München;BARC / Alexander Thamm;;1;Carsten;Bange;BARC;Data Analytics / BI / AI;;https://www.datafestival.de/;;
43;DATA festival München;16.06.2026;17.06.2026;München;BARC / Alexander Thamm;;2;Alexander;Thamm;Alexander Thamm GmbH;Data Science / AI;;https://www.datafestival.de/;;
44;K5 Konferenz 2026;23.06.2026;24.06.2026;Berlin;K5;;1;Saskia;Meier-Andrae;;E-Commerce / Retail;;https://konferenz.k5.de/;;
44;K5 Konferenz 2026;23.06.2026;24.06.2026;Berlin;K5;;2;Aimie-Sarah;Carstensen;;E-Commerce / Retail (Gründerin);;https://konferenz.k5.de/;;
44;K5 Konferenz 2026;23.06.2026;24.06.2026;Berlin;K5;;3;Alexander;Dumke;;Global eCommerce;;https://konferenz.k5.de/;;
44;K5 Konferenz 2026;23.06.2026;24.06.2026;Berlin;K5;;4;Alexander;Erpenbach;;E-Commerce / Versicherung;;https://konferenz.k5.de/;;
44;K5 Konferenz 2026;23.06.2026;24.06.2026;Berlin;K5;;5;Alexander;Graf;;Media / E-Commerce Publishing;;https://konferenz.k5.de/;;
44;K5 Konferenz 2026;23.06.2026;24.06.2026;Berlin;K5;;6;Anja;Ittrich;;VP E-Commerce & Marketing;;https://konferenz.k5.de/;;
45;DWX – Developer World;29.06.2026;02.07.2026;Mannheim;Developer Media;;1;;;Entwickler-Community DACH;AI / Cloud / Web / .NET;;https://www.developer-world.de/;;
46;GITEX Europe 2026;30.06.2026;01.07.2026;Berlin;GITEX / DWTC;;1;;;Internationale Tech-Leader;AI / Cloud / Cybersecurity;;https://www.gitexeurope.com/;;
47;Strategiegipfel Cyber Security;01.07.2026;02.07.2026;Berlin;project networks;;1;;;CISOs / Security-Strategen;Cybersecurity / NIS2 / KRITIS;;https://www.project-networks.com/;;
48;Sophos Partner Roadshow;Diverse 2026;Diverse 2026;München, Essen, Hamburg, Frankfurt;Sophos;;1;Olaf;Kaiser;Systemhaus-Coach;MSP-Geschäftsmodelle;;https://partnernews.sophos.com/;;
49;Sophos MSP Community Days;Frühjahr 2026;Frühjahr 2026;München, Essen;Sophos;;1;Olaf;Kaiser;Systemhaus-Coach;MSP-Community / Praxisimpulse;;https://partnernews.sophos.com/;;
50;WeAreDevelopers World Congress;08.07.2026;10.07.2026;Berlin;WeAreDevelopers;;1;Werner;Vogels;Amazon (CTO);Technology Leadership / Cloud;;https://www.wearedevelopers.com/;;
50;WeAreDevelopers World Congress;08.07.2026;10.07.2026;Berlin;WeAreDevelopers;;2;Karsten;Wildberger;BM Digitales;Digital Transformation / Government;;https://www.wearedevelopers.com/;;
51;KI-Festival Heilbronn;25.07.2026;26.07.2026;Heilbronn;KI-Festival;;1;;;Regionale KI-Experten;KI für alle Branchen;;https://www.ki-festival.de/;;
52;Hessen Digital;20.08.2026;20.08.2026;Bad Homburg;Hessen Digital;;1;Kristina;Sinemus;;Digitalisierung Hessen;;https://www.hedigital.de/;;
53;Container Days;02.09.2026;04.09.2026;Hamburg;Container Days;;1;;;Cloud-Native/DevOps Community;Kubernetes / Docker / GitOps;;https://www.containerdays.io/;;
54;Handelsblatt Summit Zukunft IT;07.09.2026;09.09.2026;Düsseldorf;Handelsblatt;;1;Karsten;Wildberger;BM Digitales;Digitalpolitik;;https://live.handelsblatt.com/;;
54;Handelsblatt Summit Zukunft IT;07.09.2026;09.09.2026;Düsseldorf;Handelsblatt;;2;Bernd;Rattey;Deutsche Bahn;CIO / IT-Strategie;;https://live.handelsblatt.com/;;
54;Handelsblatt Summit Zukunft IT;07.09.2026;09.09.2026;Düsseldorf;Handelsblatt;;3;Dirk;Schrödter;CIO Schleswig-Holstein;Digitale Verwaltung;;https://live.handelsblatt.com/;;
55;ChannelPartner Kongress;15.09.2026;16.09.2026;Frankfurt;ChannelPartner / IDG;;1;;;Channel-Experten / Systemhaus-Chefs;IT-Channel / Cloud / Security;;https://event.foundryco.com/;;
56;German Datacenter Conference;16.09.2026;17.09.2026;Bad Vilbel;German Datacenter Association;;1;;;RZ-Experten / Politik;Infrastruktur / Nachhaltigkeit / AI;;https://www.gdc-conference.com/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;1;Karsten;Wildberger;BM Digitales;Digitalpolitik / AI;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;2;Tim;Höttges;Deutsche Telekom (CEO);Telecom / Digital Transformation;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;3;Frank;Thelen;Freigeist Capital;Innovation / Investment;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;4;Marianne;Janik;Google Cloud (VP EMEA North);Cloud Technology;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;5;Rolf;Schumann;Schwarz-Digits;AI / Technology;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;6;Christian;Wulff;Ex-Bundespräsident;Technology / Policy;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;7;Björn;Ommer;LMU München;Computer Vision / AI;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;8;Christoph;Straub;Barmer (CEO);Healthcare / Digital;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;9;Franziska;Brantner;Die Grünen;AI Policy;;https://bigbangfestival.de/;;
57;BIG BANG KI Festival;16.09.2026;17.09.2026;Berlin;BIG BANG AI;;10;Carsten;Schneider;Bundesregierung;Umwelt & Klima;;https://bigbangfestival.de/;;
58;AI & Data Summit / Quantum Summit;22.09.2026;23.09.2026;Berlin;AIDA;;1;Dorothee;Bär;BMFTR;Forschung / KI / Quantentechnologie (BMFTR);;https://aidaq.berlin/;;
59;Confare #CIOSUMMIT Frankfurt;23.09.2026;23.09.2026;Frankfurt;Confare;;1;;;CIOs / IT-Leader DACH;IT-Strategie / Transformation / Leadership;;https://confare.at/;;
60;DMEXCO 2026;23.09.2026;24.09.2026;Köln;Koelnmesse;;1;Aude;Gandon;Nestlé (Global CMO);Marketing / Digital;;https://dmexco.com/;;
60;DMEXCO 2026;23.09.2026;24.09.2026;Köln;Koelnmesse;;2;Tim;Alexander;Deutsche Bank (CMO);Marketing / Finance;;https://dmexco.com/;;
60;DMEXCO 2026;23.09.2026;24.09.2026;Köln;Koelnmesse;;3;Nadine;Kamski;L'Oréal;Media Director DACH;;https://dmexco.com/;;
60;DMEXCO 2026;23.09.2026;24.09.2026;Köln;Koelnmesse;;4;Christian;Raveaux;REWE Group;Customer Insights & Media;;https://dmexco.com/;;
60;DMEXCO 2026;23.09.2026;24.09.2026;Köln;Koelnmesse;;5;Thomas;Wlazik;TikTok (GM DACH);Social Media / Marketing;;https://dmexco.com/;;
61;Rethink! Cloud & Data Security Summit;23.09.2026;25.09.2026;München;we.CONECT;;1;;;310+ CISOs / Cloud-Security-Experten;Cloud Security / SASE / IAM / Zero Trust;;https://www.we-conect.com/;;
62;Rethink! IT Security Herbst;24.09.2026;25.09.2026;Berlin;we.CONECT;;1;Björn H.;Friedrich;Deutsche Telekom;Cloud Security / Zero Trust;;https://www.we-conect.com/;;
63;Cloud & Datacenter Conference Germany;30.09.2026;01.10.2026;Hanau;Rachfahl IT-Solutions;;1;Carsten;Rachfahl;Rachfahl IT-Solutions;Hyper-V / Azure Stack HCI;Microsoft MVP;https://www.cdc-germany.de/;;
63;Cloud & Datacenter Conference Germany;30.09.2026;01.10.2026;Hanau;Rachfahl IT-Solutions;;2;Didier;Van Hoye;;Windows Server / Hybrid Cloud;Microsoft MVP;https://www.cdc-germany.de/;;
63;Cloud & Datacenter Conference Germany;30.09.2026;01.10.2026;Hanau;Rachfahl IT-Solutions;;3;Eric;Berg;;Azure / Cloud;Microsoft MVP;https://www.cdc-germany.de/;;
63;Cloud & Datacenter Conference Germany;30.09.2026;01.10.2026;Hanau;Rachfahl IT-Solutions;;4;Thomas;Maurer;;Azure / Hybrid Cloud;Microsoft MVP;https://www.cdc-germany.de/;;
63;Cloud & Datacenter Conference Germany;30.09.2026;01.10.2026;Hanau;Rachfahl IT-Solutions;;5;Marcel;Zehner;;Cloud & Datacenter Management;Microsoft MVP;https://www.cdc-germany.de/;;
64;digital X 2026;08.09.2026;08.09.2026;Köln;Deutsche Telekom;;1;Tim;Höttges;Deutsche Telekom (CEO);Digitalisierung / Megatrends;;https://www.digital-x.eu/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;1;Immanuel;Bär;;White-Hat-Hacker / Cybersecurity;;https://www.inno-it.de/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;2;Wolfgang;Ehrk;DIERCK GROUP (CEO);IT-Panel;;https://www.inno-it.de/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;3;Tommy;Grosche;Fortinet;Network Security;;https://www.fortinet.com/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;4;Andreas;Livert;Extreme Networks;VP Sales DACH / Networking;;https://www.inno-it.de/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;5;Sylvia;Lösel;IT-Business Magazin;IT Media / Panel;;https://www.inno-it.de/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;6;Isabell;Welpe;TUM;Strategie / AI;;https://www.inno-it.de/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;7;Katharina;Zweig;TU Kaiserslautern;AI / Algorithmen;;https://www.inno-it.de/;;
65;INNO IT Kiel;Herbst 2026;Herbst 2026;Kiel;DIERCK GROUP;;8;Mojib;Latif;GEOMAR;Klimaforschung & AI;;https://www.inno-it.de/;;
66;IT.CON 2026;Herbst 2026;Herbst 2026;Saarbrücken;CYBR360;;1;August-Wilhelm;Scheer;;IT / Wirtschaftsinformatik;;https://it-con-messe.de/;;
67;Rethink! AI & Cybersecurity Summit;Herbst 2026;Herbst 2026;München;we.CONECT;;1;;;CISOs / AI-Security-Experten;KI-gestützte Abwehr / Threat Intelligence;;https://www.we-conect.com/;;
68;it-sa Expo&Congress 2026;27.10.2026;29.10.2026;Nürnberg;NürnbergMesse;;1;;;Internationale IT-Security-Experten;IT-Sicherheit / 400+ Präsentationen;;https://www.itsa365.de/;;
69;ISX IT-Security Digital Conference;17.11.2026;17.11.2026;Online;Vogel IT-Akademie;;1;;;Security-Experten;IT-Security (virtuell);;https://www.security-insider.de/;;
70;Azure Summit 2026;24.11.2026;24.11.2026;Virtuell (DE);IT-Schulungen.com;;1;;;Azure MVPs / Experten;Azure / Security / AI / DevOps;MVP;https://www.it-schulungen.com/;;
71;PMRExpo 2026;24.11.2026;26.11.2026;Köln;PMeV / Koelnmesse;;1;Martin;Kasparick;;Sichere Kommunikation;;https://www.pmrexpo.com/;;
71;PMRExpo 2026;24.11.2026;26.11.2026;Köln;PMeV / Koelnmesse;;2;Helen;Kuhlmann;;KRITIS / BOS-Digitalfunk;;https://www.pmrexpo.com/;;
72;IT-Tage 2026;07.12.2026;10.12.2026;Frankfurt;Informatik Aktuell;;1;;;270+ Top-Speaker DACH;Cloud / Security / DevOps / KI / Java / .NET;;https://www.ittage.informatik-aktuell.de/;;
73;Dynamics Summit 2026;08.12.2026;08.12.2026;Virtuell (DE);IT-Schulungen.com;;1;;;Dynamics 365 / Power Platform Experten;Dynamics 365 / Business Apps;;https://www.it-schulungen.com/;;
74;CCW 2026 – Kongressmesse Kundendialog;23.02.2026;26.02.2026;Berlin;Management Circle;;1;Peter;Gentsch;;Digital Disruption / AI;;https://www.ccw.eu/;;
74;CCW 2026 – Kongressmesse Kundendialog;23.02.2026;26.02.2026;Berlin;Management Circle;;2;Marcus;Keupp;;Business / Strategie;;https://www.ccw.eu/;;
74;CCW 2026 – Kongressmesse Kundendialog;23.02.2026;26.02.2026;Berlin;Management Circle;;3;Fabian;Hemmert;;User Experience / Technology;;https://www.ccw.eu/;;
74;CCW 2026 – Kongressmesse Kundendialog;23.02.2026;26.02.2026;Berlin;Management Circle;;4;Christian;Wuttke;Schwarz Group;Chat & Voice Technology;;https://www.ccw.eu/;;
74;CCW 2026 – Kongressmesse Kundendialog;23.02.2026;26.02.2026;Berlin;Management Circle;;5;Philipp;Heltewig;NiCE;Conversational AI;;https://www.ccw.eu/;;
74;CCW 2026 – Kongressmesse Kundendialog;23.02.2026;26.02.2026;Berlin;Management Circle;;6;Anna;Mutska;;Conversational AI;;https://www.ccw.eu/;;
75;SAP TechEd Berlin 2026;27.10.2026;29.10.2026;Berlin;SAP;;1;;;SAP-Experten;SAP Technologies / Cloud / AI;;https://www.sap.com/;;
76;AWS Summit Hamburg 2026;20.05.2026;20.05.2026;Hamburg;Amazon Web Services;;1;;;AWS-Experten;Cloud / AI / 150+ Sessions;;https://aws.amazon.com/events/summits/hamburg/;;
77;Google Cloud Summit DACH 2026;09.06.2026;10.06.2026;Frankfurt;Google Cloud;;1;;;Google Cloud Experten;AI Agents / Cloud Infrastructure;;https://cloud.google.com/;;
78;Salesforce Agentforce World Tour Frankfurt;Juni 2026;Juni 2026;Frankfurt;Salesforce;;1;;;Salesforce-Experten;AI-powered Agentforce / CRM;;https://www.salesforce.com/;;
79;Cisco Connect Germany 2026;27.03.2026;28.03.2026;Hamburg;Cisco;;1;;;Cisco-Experten;Networking / Security / Collaboration;;https://www.cisco.com/;;
80;Bechtle Microsoft World 2026;10.06.2026;10.06.2026;Offenburg;Bechtle;;1;;;Microsoft & Bechtle Experten;Microsoft Technologies / 70+ Keynotes;;https://www.bmsw.live/;;
81;Ingram Micro Solution Summit 2026;19.03.2026;19.03.2026;München;Ingram Micro;;1;;;Microsoft, AMD, IBM, Dell, HPE Experten;AI / Security / Cloud / Mittelstand;;https://www.ingrammicro.de/;;
82;DACHsec IT Security Summit 2026;15.04.2026;16.04.2026;Frankfurt;Cyber Series;;1;Florian;Augthun;Ströer SE;Cyber Security Architecture;;https://dach.cyberseries.io/;;
82;DACHsec IT Security Summit 2026;15.04.2026;16.04.2026;Frankfurt;Cyber Series;;2;Daniel;Maier-Johnson;Kuehne+Nagel;CISO / Enterprise Cybersecurity;;https://dach.cyberseries.io/;;
82;DACHsec IT Security Summit 2026;15.04.2026;16.04.2026;Frankfurt;Cyber Series;;3;Chuks;Ojeme;;Cybersecurity Thought Leadership;;https://dach.cyberseries.io/;;
82;DACHsec IT Security Summit 2026;15.04.2026;16.04.2026;Frankfurt;Cyber Series;;4;Katarzyna;Bukowska;;Cyber Security Operations;;https://dach.cyberseries.io/;;
83;gamescom 2026;26.08.2026;30.08.2026;Köln;Koelnmesse;;1;;;Gaming / VR / AR Experten;Gaming Tech / VR / AR / Hardware;;https://www.gamescom.de/;;
84;Käpsele Innovation Festival;16.07.2026;16.07.2026;Freiburg;SICK ARENA;Innovation Festival;;;;Größtes Innovationsfestival Südwestdeutschlands;;;https://www.innovation-festival.de/;Unabhängig;Kostenpflichtig
85;SmashingConf Freiburg;07.09.2026;10.09.2026;Freiburg;Historisches Kaufhaus;Smashing Magazine;;;;Frontend, UX, Design, CSS, JavaScript, Web Performance;;;https://smashingconf.com/;Unabhängig;Kostenpflichtig
86;LEARNTEC 2026;05.05.2026;07.05.2026;Karlsruhe;Messe Karlsruhe;Messe Karlsruhe;;;;Europas größtes Digital-Education-Event. 10.000+ Besucher;;;https://www.learntec.de/;Unabhängig;Kostenpflichtig
87;Karlsruher Entwicklertag 2026;08.06.2026;10.06.2026;Karlsruhe;IHK Karlsruhe;Entwicklertag e.V.;;;;Developer-Konferenz Softwareentwicklung;;;https://www.entwicklertag.de/;Unabhängig;Kostenpflichtig
88;Minds Mastering Machines (M3);22.04.2026;23.04.2026;Karlsruhe;Karlsruhe;heise / dpunkt;;;;Data-Science-Konferenz: ML, AI, Data Engineering;;;https://m3-konferenz.de/;Unabhängig;Kostenpflichtig
89;Industrial Technology Summit 2026;19.05.2026;19.05.2026;Stuttgart;Stuttgart;ITS;;;;AI und ML in der Industrie, AI-Security;;;https://www.stuttgart.de/;Unabhängig;Kostenpflichtig
90;ELO Horizons 2026;19.03.2026;19.03.2026;Stuttgart;Liederhalle;ELO Digital Office;;;;Digitalisierung, AI, E-Invoicing;;;https://www.elo.com/;Firmenevent;Kostenfrei
91;Baden-Württemberg 4.0 Kongress;02.07.2026;02.07.2026;Stuttgart;Maritim Hotel;BW 4.0;;;;Digitale Verwaltung, OZG, AI, Cloud;;;https://www.bw-4-0.de/;Unabhängig;Kostenpflichtig
92;Controlware IT-Security Roadshow Stuttgart;18.03.2026;18.03.2026;Stuttgart;Porsche Museum;Controlware;;;;IT-Security, AI-Services, LLM-Schutz, Mesh-Firewall;;;https://www.controlware.de/;Firmenevent;Kostenfrei
93;CyberCompare SecurityDays Stuttgart;2026;2026;Stuttgart;Stuttgart;CyberCompare;;;;Cybersecurity Trends & Challenges;;;https://cybercompare.com/securitydays/;Unabhängig;€300 (Promo kostenlos)
94;Autonomous Vehicle Technology Expo;23.06.2026;25.06.2026;Stuttgart;Messe Stuttgart;Vehicle Tech Week;;;;Autonomous Vehicles, AI, SDV, Simulation;;;https://autonomousvehicletechnologyexpo.com/;Unabhängig;Kostenpflichtig
95;IT meets Industry (IMI26) OT-Security;19.05.2026;19.05.2026;Mannheim;MAFINEX Technology Center;IMI;;;;OT-Security Congress, Industrial Cybersecurity;;;https://www.it-meets-industry.de/;Unabhängig;Kostenpflichtig
96;trade/off Summit 2026;12.05.2026;12.05.2026;Heidelberg;Heidelberg;trade/off;;;;AI meets Business Growth. 30+ Speaker, 700+ Executives;;;https://www.tradeoff.ai/;Unabhängig;Kostenpflichtig
97;ITCS Köln 2026;18.09.2026;18.09.2026;Köln;XPOST;ITCS Conference;;;;Tech-Konferenz, IT-Karrieremesse, AI, Data Science, Cloud;;;https://it-cs.io/;Unabhängig;Kostenfrei
98;European AI & Cloud Summit 2026;05.05.2026;07.05.2026;Köln;Koelnmesse;Cloud Summit;;;;Europas größte AI/Azure/OpenAI/Cloud-Konferenz. 3.000+ Teilnehmer, 250+ Sessions;;;https://cloudsummit.eu/;Unabhängig;Kostenpflichtig
99;KI Day 2026;17.09.2026;17.09.2026;Köln;RheinEnergieStadion;KI Day;;;;AI-Konferenz für Unternehmen;;;https://ki-day.de/;Unabhängig;Kostenpflichtig
100;Generative AI Summit 2026 (Arvato/Microsoft);21.04.2026;21.04.2026;Köln;Microsoft Office Rheinauhafen;Arvato / Microsoft;;;;Summit Generative AI Technologies;;;https://us.arvato-systems.com/;Firmenevent;Kostenpflichtig
101;digitalBAU 2026;24.03.2026;26.03.2026;Köln;Koelnmesse;digitalBAU;;;;Digitale Transformation Bauindustrie: BIM, Smart Building, AI;;;https://www.digitalbau.com/;Unabhängig;Kostenpflichtig
102;ANGA COM 2026;19.05.2026;21.05.2026;Köln;Koelnmesse;ANGA COM;;;;Europas führende Broadband/Media/Connectivity-Plattform;;;https://angacom.de/;Unabhängig;Kostenpflichtig
103;Future Tech Fest 2026;10.09.2026;10.09.2026;Düsseldorf;Düsseldorf;Future Tech Fest;;;;Startup Week Highlight. 250+ Tech-Startups;;;https://www.futuretechfest.de/;Unabhängig;Kostenpflichtig
104;beyond tellerrand Düsseldorf 2026;27.04.2026;28.04.2026;Düsseldorf;Düsseldorf;beyond tellerrand;;;;Creativity meets Technology Konferenz;;;https://beyondtellerrand.com/;Unabhängig;Kostenpflichtig
105;MEDICA 2026;16.11.2026;19.11.2026;Düsseldorf;Messe Düsseldorf;Messe Düsseldorf;;;;Weltleitmesse Medizintechnik. 5.000+ Aussteller, 80.000+ Besucher;;;https://www.medica-tradefair.com/;Unabhängig;Kostenpflichtig
106;XPONENTIAL Europe 2026;24.03.2026;26.03.2026;Düsseldorf;Messe Düsseldorf;XPONENTIAL;;;;Autonomous Systems, Robotics, Drohnen;;;https://www.xponential-europe.com/;Unabhängig;Kostenpflichtig
107;IJCAI-ECAI 2026;15.08.2026;21.08.2026;Bremen;Uni Bremen / Exhibition Center;IJCAI;;;;Weltgrößte KI-Forschungskonferenz. 3.500-4.000 Experten;;;https://ijcai-26.org/;Unabhängig;Kostenpflichtig
108;Space Tech Expo Bremen;17.11.2026;19.11.2026;Bremen;Bremen;Space Tech Expo;;;;Aerospace Technology, Raumfahrt-IT;;;https://www.spacetechexpo-europe.com/;Unabhängig;Kostenpflichtig
109;ITCS Hamburg 2026;26.06.2026;26.06.2026;Hamburg;Messe Hamburg Hall A2;ITCS Conference;;;;Tech-Konferenz, IT-Karrieremesse. 500+ Firmen, 50+ Keynotes;;;https://it-cs.io/;Unabhängig;Kostenfrei
110;code.talks 2026;04.11.2026;05.11.2026;Hamburg;Hamburg;code.talks;;;;Developer-Konferenz. 3.500+ Devs, CTOs, 100+ Sessions;;;https://codetalks.com/;Unabhängig;Kostenpflichtig
111;Agentic Conf Hamburg 2026;22.03.2026;22.03.2026;Hamburg;SAE Institute;Agentic;;;;AI Agent Development Conference. 200-300 Practitioners;;;https://agentic.hamburg/;Unabhängig;Kostenpflichtig
112;techcamp Hamburg 2026;2026;2026;Hamburg;Klubhaus on the Kiez;techcamp;;;;Tech Talks auf 5 Bühnen, Networking;;;https://techcamp.hamburg/;Unabhängig;Kostenpflichtig
113;Tech SEO Summit 2026;23.04.2026;23.04.2026;Hamburg;Hamburg;Tech SEO Summit;;;;Advanced Technical SEO;;;https://tech-seo-summit.com/;Unabhängig;Kostenpflichtig
114;re:publica Berlin 2026;18.05.2026;20.05.2026;Berlin;STATION Berlin;re:publica;;;;Europas größtes Digital Society Festival;;;https://re-publica.com/;Unabhängig;Kostenpflichtig
115;Deep Tech Momentum 2026 (DTM26);20.05.2026;21.05.2026;Berlin;Berlin;Deep Tech;;;;3.000+ Corporate Leaders, Investors, Deep Tech Founders;;;https://www.deeptech.build/;Unabhängig;Kostenpflichtig
116;OffensiveCon Berlin 2026;15.05.2026;16.05.2026;Berlin;Berlin;OffensiveCon;;;;Offensive Security, Vulnerability Discovery, Reverse Engineering;;;https://www.offensivecon.org/;Unabhängig;Kostenpflichtig
117;ESET World 2026;18.05.2026;21.05.2026;Berlin;JW Marriott Berlin;ESET;;;;Global Security Conference: CISOs, Threat Hunters;;;https://esetworld.com/;Firmenevent;Kostenpflichtig
118;Accelerate Tomorrow AI Summit 2026;02.06.2026;03.06.2026;Berlin;ESTREL Berlin;Accelerate Tomorrow;;;;Deutschlands führendes AI-Event. 2.000+ Executives, 200+ AI-Speaker;;;https://www.acceleratetomorrow.de/;Unabhängig;Kostenpflichtig
119;IFA 2026;04.09.2026;08.09.2026;Berlin;Messe Berlin;Messe Berlin;;;;Consumer Electronics & Tech Trade Show;;;https://www.ifa-berlin.com/;Unabhängig;Kostenpflichtig
120;ECOSUMMIT.AI Berlin 2026;25.03.2026;26.03.2026;Berlin;Berlin;Ecosummit;;;;AI Startups & Investors, Climate Tech, Decarbonization;;;https://ecosummit.net/;Unabhängig;Kostenpflichtig
121;AWS Community Day DACH 2026;15.09.2026;15.09.2026;Berlin;Kosmos Berlin;AWS Community;;;;AWS Community Event DACH;;;https://aws.amazon.com/;Community;Kostenpflichtig
122;Berlin Security Conference 2026;03.11.2026;04.11.2026;Berlin;Vienna House Andel's;BSC;;;;Europas größtes Security & Defence Policy Event;;;https://www.euro-defence.eu/;Unabhängig;Kostenpflichtig
123;IT-Woche Leipzig 2026;2026;2026;Leipzig;Leipzig;Leipzig Convention;;;;5 IT-Konferenzen: Security, Cloud, AI, Versicherungen;;;https://www.leipzig-convention.com/;Unabhängig;Kostenpflichtig
124;Data Week Leipzig 2026;01.06.2026;05.06.2026;Leipzig;Leipzig;Data Week;;;;Data & AI: Digitale Stadt, Klima, Energie;;;https://2026.dataweek.de/;Unabhängig;Kostenpflichtig
125;MACHN Festival Leipzig 2026;03.06.2026;04.06.2026;Leipzig;Baumwollspinnerei;MACHN Festival;;;;Business Festival. 100+ Sessions, 70+ Speaker, AI BarCamp;;;https://machn-festival.de/;Unabhängig;Kostenpflichtig
126;heise Jobs IT Tag Leipzig;24.03.2026;24.03.2026;Leipzig;Leipzig;heise medien;;;;IT-Karrieremesse;;;https://www.heise.de/;Unabhängig;Kostenfrei
127;d:u (data:unplugged) 2026;26.03.2026;27.03.2026;Halle;MCC Halle Münsterland;data:unplugged;;;;Deutsche Data & AI Scene. 10.000+ Teilnehmer;;;https://www.data-unplugged.de/;Unabhängig;Kostenpflichtig
128;KH IT-Frühjahrstagung 2026;20.05.2026;21.05.2026;Kassel;KulturBahnhof;Bundesverband KH-IT;;;;Krankenhaus-IT: Praxis, Recht, Networking;;;;Branchenspezifisch;Kostenpflichtig
129;Technorama Kassel 2026;21.03.2026;22.03.2026;Kassel;Messe Kassel;Messe Kassel;;;;Technologie & Innovation: Robotics, AI, Nachhaltigkeit;;;https://www.technorama-kassel.de/;Unabhängig;Kostenpflichtig
130;The Founder Summit 2026;11.04.2026;12.04.2026;Wiesbaden;RheinMain CongressCenter;Founder Summit;;;;Business & Startup Festival. 120+ Speaker, 17.000+ Besucher, 175+ Aussteller;;;https://www.foundersummit.de/;Unabhängig;Kostenpflichtig
131;Cloud X Summit 2026;16.09.2026;17.09.2026;Wiesbaden;Wiesbaden;Cloud X Summit;;;;4. Cloud X Summit;;;;Unabhängig;Kostenpflichtig
132;Wireless IoT Tomorrow 2026;28.10.2026;29.10.2026;Wiesbaden;Wiesbaden;WIoT;;;;RFID, NFC, BLE, UWB, Sensing – Europas führende IoT-Plattform;;;https://wiot-group.com/tomorrow/;Unabhängig;Kostenpflichtig
133;ITCS München 2026;30.10.2026;30.10.2026;München;MOC Event Centre;ITCS Conference;;;;Tech-Konferenz & IT-Karrieremesse. 500+ Firmen, 5 Stages;;;https://it-cs.io/;Unabhängig;Kostenfrei
134;MLcon Munich 2026;22.06.2026;26.06.2026;München;Holiday Inn City Center;MLcon;;;;Machine Learning & Generative AI Konferenz + Workshops;;;https://mlconference.ai/munich/;Unabhängig;Kostenpflichtig
135;Big Techday 26;22.05.2026;22.05.2026;München;Motorworld München;TNG Technology;;;;Tech Day Event mit Top-Speakern;;;https://www.bigtechday.com/;Firmenevent;Kostenfrei
136;Helmholtz AI Conference 2026;08.06.2026;11.06.2026;München;München;Helmholtz AI;;;;AI-Forschungskonferenz der Helmholtz-Zentren;;;https://www.helmholtz.ai/;Forschung;Kostenpflichtig
137;Munich Cyber Security Conference;12.02.2026;13.02.2026;München;IHK München;MCSC;;;;Prestigious Cybersecurity Forum. C-Level, 150+ Senior Leaders;;;https://mcsc.io/;Einladung;Auf Einladung
138;Conversational AI & CX Summit Europe;27.10.2026;29.10.2026;München;Leonardo Royal Hotel;CX Summit;;;;5. Ausgabe: Conversational AI, Customer Experience;;;https://conversationaltechsummit.com/;Unabhängig;Kostenpflichtig
139;Nextcloud Digital Sovereignty Summit;09.06.2026;09.06.2026;München;München;Nextcloud;;;;Digitale Souveränität, Sichere Collaboration;;;https://nextcloud.com/;Firmenevent;Kostenpflichtig
140;SANS Munich Training June 2026;Juni 2026;Juni 2026;München;München;SANS Institute;;;;Cybersecurity Training Courses;;;https://www.sans.org/;Training;Kostenpflichtig
141;DIGITAL X Mitte 2026;03.03.2026;03.03.2026;Mainz;MEWA Arena;Deutsche Telekom;;;;Regionales Digitalisierungs-Event;;;https://www.digital-x.eu/;Firmenevent;Kostenfrei
142;health.tech Global Summit 2026;03.03.2026;05.03.2026;Basel;Messe Basel;health.tech;;;;Healthcare & Technology. 6.000 Teilnehmer, 120+ Startups;;;https://www.health.tech/;Unabhängig;Kostenpflichtig
143;BioTechX Europe 2026;2026;2026;Basel;Messe Basel;Terrapinn;;;;3.000+ Leaders: Diagnostics, Precision Medicine, AI, Genomics;;;https://www.terrapinn.com/;Unabhängig;Kostenpflichtig
144;all about automation Friedrichshafen;10.03.2026;11.03.2026;Friedrichshafen;Messe Friedrichshafen;all about automation;;;;Automation, Robotics, AI/ML, Nachhaltigkeit. 500+ Aussteller;;;https://www.allaboutautomation.de/;Unabhängig;Kostenpflichtig
145;VMware Explore on Tour Frankfurt;13.10.2026;14.10.2026;Frankfurt;Frankfurt;Broadcom/VMware;;;;VMware/Broadcom Cloud, Virtualisierung, Security;;;https://www.vmware.com/explore;Firmenevent;Kostenpflichtig
146;Oracle AI World Tour Frankfurt;12.03.2026;12.03.2026;Frankfurt;Kap Europa;Oracle;;;;AI World Tour: Oracle Cloud, AI, Data;;;https://www.oracle.com/ai-world-tour/;Firmenevent;Kostenfrei
147;Commvault SHIFT Zürich;07.05.2026;07.05.2026;Zürich;Marriott Hotel;Commvault;;;;Data Management, Cloud, Cyber Resilience;;;https://www.commvault.com/shift-roadshow;Firmenevent;Kostenfrei
148;NetApp INSIGHT Xtra Zürich;12.03.2026;12.03.2026;Zürich;The Circle Zurich Airport;NetApp;;;;Data Management, Cloud, AI;;;https://www.netapp.com/insight/;Firmenevent;Kostenfrei
149;DIGITAL X Köln (Hauptevent);08.09.2026;08.09.2026;Köln;Rheinauhafen;Deutsche Telekom;;;;Europas führendes Digitalisierungsevent (Hauptevent);;;https://www.digital-x.eu/;Firmenevent;Kostenfrei
CSV;

$lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
$header = str_getcsv((string) array_shift($lines), ';', '"', '') ?: [];
$normalizeHeader = static function (string $value): string {
    $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
    $value = str_replace(['ä', 'ö', 'ü', 'ß', '/', ' '], ['ae', 'oe', 'ue', 'ss', '_', '_'], $value);
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    return trim($value, '_');
};
$headers = array_map($normalizeHeader, $header);
$rows = [];
foreach ($lines as $line) {
    if (trim($line) === '') {
        continue;
    }
    $values = str_getcsv($line, ';', '"', '') ?: [];
    $row = [];
    foreach ($headers as $idx => $key) {
        if ($key !== '') {
            $row[$key] = trim((string) ($values[$idx] ?? ''));
        }
    }
    $rows[] = $row;
}

$eventDescriptionsByNr = [];
$descriptionMapFile = __DIR__ . '/_generated_event_descriptions_map.php.txt';
if (is_file($descriptionMapFile)) {
    $loadedDescriptions = require $descriptionMapFile;
    if (is_array($loadedDescriptions)) {
        $eventDescriptionsByNr = $loadedDescriptions;
    }
}

foreach ($rows as &$row) {
    $nr = (int) ($row['nr'] ?? 0);
    $row['description'] = (string) ($eventDescriptionsByNr[$nr] ?? '');
}
unset($row);

return $rows;
