# Rank Math API Manager Plugin

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![GitHub downloads](https://img.shields.io/github/downloads/devora-as/rank-math-api-manager/total.svg)](https://github.com/devora-as/rank-math-api-manager/releases)
[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net/)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0+-green.svg)](https://wordpress.org/)

## 📋 Oversikt

**Plugin Name**: Rank Math API Manager  
**Version**: 1.0.6
**Author**: Devora AS  
**Description**: WordPress-plugin som eksponerer REST API-endepunkter for å oppdatere Rank Math SEO-metadata programmatisk.

## 🎯 Formål

Dette plugin-et utvider WordPress REST API med tilpassede endepunkter som lar eksterne systemer (som n8n workflows) oppdatere Rank Math SEO-felter direkte via API-kall. Dette eliminerer behovet for manuell SEO-konfigurasjon og integrerer sømløst med automatisering.

## ✨ Funksjoner

### 🔧 SEO-felt som støttes

- **SEO Title** (`rank_math_title`) - Meta-tittel for søkemotorer
- **SEO Description** (`rank_math_description`) - Meta-beskrivelse for søkemotorer
- **Canonical URL** (`rank_math_canonical_url`) - Kanonisk URL for duplikatinnhold
- **Focus Keyword** (`rank_math_focus_keyword`) - Hovedsøkeord for artikkelen

### 🌐 REST API Endepunkter

#### POST `/wp-json/rank-math-api/v1/update-meta`

Oppdaterer Rank Math SEO-metadata for et spesifikt innlegg eller produkt.

**Parametere:**

- `post_id` (påkrevd) - ID til innlegget/produktet
- `rank_math_title` (valgfritt) - SEO-tittel
- `rank_math_description` (valgfritt) - SEO-beskrivelse
- `rank_math_canonical_url` (valgfritt) - Kanonisk URL
- `rank_math_focus_keyword` (valgfritt) - Fokusord

**Eksempel på forespørsel:**

```bash
curl -X POST "https://example.com/wp-json/rank-math-api/v1/update-meta" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Authorization: Basic [base64-encoded-credentials]" \
  -d "post_id=123&rank_math_title=Optimalisert tittel&rank_math_description=SEO beskrivelse&rank_math_focus_keyword=søkeord"
```

**Respons:**

```json
{
  "rank_math_title": "updated",
  "rank_math_description": "updated",
  "rank_math_focus_keyword": "updated"
}
```

## 🚀 Installasjon

### 1. Plugin-installasjon

1. Last opp `rank-math-api-manager.php` til `/wp-content/plugins/rank-math-api-manager/`
2. Aktiver plugin-et i WordPress admin-panel
3. Verifiser at plugin-et er aktivt

### 2. Tillatelser

Plugin-et krever at brukeren har `edit_posts`-rettigheter for å oppdatere metadata.

### 3. REST API-tilgang

Sørg for at WordPress REST API er tilgjengelig og ikke blokkert av sikkerhetslag.

## 🔗 Integrasjon med n8n Workflow

Dette plugin-et er spesielt designet for å fungere med Devora sin n8n workflow "Write wordpress post with AI".

### Workflow-integrasjon

1. **Automatisk SEO-generering**: AI-genererer SEO-metadata basert på innhold
2. **Programmatisk oppdatering**: n8n sender API-kall til plugin-et
3. **Sømløs integrasjon**: Ingen manuell intervensjon nødvendig

### n8n Node-konfigurasjon

```json
{
  "method": "POST",
  "url": "https://example.com/wp-json/rank-math-api/v1/update-meta",
  "contentType": "form-urlencoded",
  "bodyParameters": {
    "post_id": "={{ $('Post on Wordpress').first().json.id }}",
    "rank_math_title": "={{ $('Generate metatitle e metadescription').first().json.output.metatitle }}",
    "rank_math_description": "={{ $('Generate metatitle e metadescription').first().json.output.metadescription }}",
    "rank_math_focus_keyword": "={{ $('Generate metatitle e metadescription').first().json.output.metakeywords }}"
  }
}
```

## 🛡️ Sikkerhet

### Autentisering

- Krever WordPress Application Password eller Basic Auth
- Validerer brukerrettigheter (`edit_posts`)
- Sanitizerer alle input-parametere

### Validering

- Validerer at `post_id` eksisterer
- Sanitizerer tekst-felter med `sanitize_text_field()`
- Validerer URL-er med `esc_url_raw()`

## 🔧 Tekniske Detaljer

### Post Types

Plugin-et støtter automatisk:

- **Posts** (standard WordPress innlegg)
- **Products** (WooCommerce produkter, hvis WooCommerce er aktivt)

### Meta Fields

Alle SEO-felter registreres som post meta med:

- `show_in_rest: true` - Tilgjengelig via REST API
- `single: true` - Enkelt verdier
- `type: string` - String-datatype
- `auth_callback` - Tillatelseskontroll

## 🗺️ Utviklingsplan (Roadmap)

### 🎯 Fase 1: Utvidet Feltstøtte (Høy Prioritet)

#### 1.1 Sosiale Medier Meta-tagger

- **Facebook Title** (`rank_math_facebook_title`)
- **Facebook Description** (`rank_math_facebook_description`)
- **Facebook Image** (`rank_math_facebook_image`)
- **Twitter Title** (`rank_math_twitter_title`)
- **Twitter Description** (`rank_math_twitter_description`)
- **Twitter Image** (`rank_math_twitter_image`)

#### 1.2 Avanserte SEO-felter

- **Robots Meta** (`rank_math_robots`)
- **Advanced Robots** (`rank_math_advanced_robots`)
- **Primary Category** (`rank_math_primary_category`)
- **Secondary Focus Keyword** (`rank_math_secondary_focus_keyword`)
- **Tertiary Focus Keyword** (`rank_math_tertiary_focus_keyword`)

#### 1.3 Schema Markup

- **Schema Type** (`rank_math_schema_type`)
- **Article Schema Type** (`rank_math_schema_article_type`)

### 🚀 Fase 2: Bulk-operasjoner og Lesefunksjoner

#### 2.1 Bulk-oppdateringer

```php
POST /wp-json/rank-math-api/v1/bulk-update
```

- Oppdater flere innlegg/produkter i én API-forespørsel
- Støtte for batch-prosessering
- Feilhåndtering for individuelle oppdateringer

#### 2.2 Lesefunksjoner

```php
GET /wp-json/rank-math-api/v1/get-meta/{post_id}
GET /wp-json/rank-math-api/v1/posts
```

- Hent eksisterende SEO-metadata
- Liste over innlegg med SEO-informasjon
- Filtrering og sortering

#### 2.3 SEO-status Endepunkt

```php
GET /wp-json/rank-math-api/v1/seo-status/{post_id}
```

- SEO-poengsum for innlegg
- Manglende felter
- Anbefalinger for forbedring
- Schema-status

### 🔄 Fase 3: Automatisering og Integrasjon

#### 3.1 Betingede Oppdateringer

```php
POST /wp-json/rank-math-api/v1/smart-update
```

- Oppdater kun hvis felter er tomme
- Oppdater kun hvis verdier er forskjellige
- Minimum/maksimum lengde-validering
- Duplikatkontroll

#### 3.2 Webhook-støtte

```php
POST /wp-json/rank-math-api/v1/webhooks
```

- Registrer webhooks for SEO-oppdateringer
- Real-time varsling ved endringer
- Konfigurerbare webhook-endepunkter

#### 3.3 SEO-mal System

```php
POST /wp-json/rank-math-api/v1/apply-template
```

- Forhåndsdefinerte SEO-maler
- Variabel-substitusjon
- Innholdsbaserte maler (blogg, produkt, side)

### 📊 Fase 4: Avanserte Funksjoner

#### 4.1 SEO-validering

```php
POST /wp-json/rank-math-api/v1/validate
```

- Validering av SEO-metadata før lagring
- Lengde-kontroller
- Søkeord-tetthet
- Duplikat-sjekk

#### 4.2 Analytics og Rapportering

```php
GET /wp-json/rank-math-api/v1/analytics
```

- SEO-statistikk for nettstedet
- Gjennomsnittlig SEO-poengsum
- Implementeringsgrad for schema
- Manglende metadata-oversikt

#### 4.3 Rate Limiting og Sikkerhet

- Rate limiting per bruker/IP
- API-nøkkel-støtte
- Audit logging
- Avansert feilhåndtering

### 🌐 Fase 5: Enterprise-funksjoner

#### 5.1 Multi-site Støtte

```php
POST /wp-json/rank-math-api/v1/multisite-update
```

- Støtte for WordPress multisite
- Cross-site SEO-synkronisering
- Sentralisert SEO-administrasjon

#### 5.2 Avanserte Integrasjoner

- Google Search Console API-integrasjon
- Google Analytics 4-integrasjon
- Eksterne SEO-verktøy-integrasjon

## 📈 Forventet Tidsplan

| Fase | Funksjoner           | Estimeret Levering | Status      |
| ---- | -------------------- | ------------------ | ----------- |
| 1    | Utvidet Feltstøtte   | Q3 2025            | 🔄 Planlagt |
| 2    | Bulk-operasjoner     | Q3 2025            | 🔄 Planlagt |
| 3    | Automatisering       | Q3 2025            | 🔄 Planlagt |
| 4    | Avanserte Funksjoner | Q4 2025            | 🔄 Planlagt |
| 5    | Enterprise           | Q1 2026            | 🔄 Planlagt |

## 🎯 Brukstilfeller

### 1. **Innholdssyndikering**

- Oppdater SEO-metadata når innhold syndikeres
- Cross-site SEO-synkronisering
- Automatisk SEO-optimalisering

### 2. **AI-drevet SEO-optimalisering**

- Integrasjon med AI-verktøy
- Automatisk søkeord-generering
- Innholdsbasert SEO-forslag

### 3. **E-handel SEO-automatisering**

- Produktkatalog-optimalisering
- Sesongbaserte kampanjer
- Lagerbasert SEO-oppdatering

### 4. **Bulk SEO-administrasjon**

- Masserapportering av innlegg
- SEO-audit-automatisering
- Konkurrentanalyse-integrasjon

## ❓ FAQ (Frequently Asked Questions)

### 🤔 Generelle Spørsmål

**Q: Hva er Rank Math API Manager?**
A: Rank Math API Manager er et WordPress-plugin som lar deg oppdatere Rank Math SEO-metadata programmatisk via REST API-endepunkter. Det er spesielt designet for å integrere med automatisering som n8n workflows.

**Q: Hvilke WordPress-versjoner støttes?**
A: Plugin-et krever WordPress 5.0 eller nyere og PHP 7.4 eller nyere.

**Q: Er Rank Math SEO-plugin påkrevd?**
A: Ja, Rank Math SEO-plugin må være installert og aktivert for at dette plugin-et skal fungere.

### 🔧 Installasjon og Oppsett

**Q: Hvordan installerer jeg plugin-et?**
A: Last opp plugin-filen til `/wp-content/plugins/rank-math-api-manager/` og aktiver den i WordPress admin-panel.

**Q: Hvilke tillatelser trenger jeg?**
A: Du må ha `edit_posts`-rettigheter for å bruke API-endepunktene.

**Q: Hvordan setter jeg opp autentisering?**
A: Bruk WordPress Application Passwords eller Basic Auth. Se installasjonsseksjonen for detaljer.

### 🌐 API og Integrasjon

**Q: Hvilke SEO-felter kan jeg oppdatere?**
A: Plugin-et støtter SEO Title, SEO Description, Canonical URL, og Focus Keyword.

**Q: Kan jeg bruke dette med WooCommerce?**
A: Ja, plugin-et støtter automatisk WooCommerce produkter hvis WooCommerce er aktivt.

**Q: Hvordan integrerer jeg med n8n?**
A: Se n8n-integrasjonsseksjonen i dokumentasjonen for eksempel-konfigurasjon.

**Q: Er det rate limiting på API-endepunktene?**
A: Plugin-et bruker WordPress' innebygde rate limiting. For høy-trafikk nettsteder anbefales ekstra rate limiting.

### 🛡️ Sikkerhet

**Q: Er API-endepunktene sikre?**
A: Ja, alle endepunkter krever autentisering og validerer brukerrettigheter. Alle input-parametere sanitizeres.

**Q: Hvordan rapporterer jeg sikkerhetsproblemer?**
A: Send sikkerhetsrapporter til security@devora.no. Ikke opprett offentlige GitHub-issues for sikkerhetsproblemer.

**Q: Logges sensitive data?**
A: Nei, plugin-et logger ikke sensitive data.

### 🔄 Oppdateringer og Vedlikehold

**Q: Hvordan oppdaterer jeg plugin-et?**
A: Plugin-et kan oppdateres via WordPress admin-panel eller ved å laste opp ny versjon manuelt.

**Q: Er det automatiske oppdateringer?**
A: Automatiske oppdateringer fra GitHub er planlagt for fremtidige versjoner.

**Q: Hvordan sjekker jeg om plugin-et fungerer?**
A: Test API-endepunktet med en enkel POST-forespørsel til `/wp-json/rank-math-api/v1/update-meta`.

### 🐛 Feilsøking

**Q: Får jeg 401 Unauthorized-feil?**
A: Sjekk at Application Password er riktig konfigurert og at brukeren har `edit_posts`-rettigheter.

**Q: Får jeg 404 Not Found-feil?**
A: Verifiser at plugin-et er aktivt og at WordPress REST API er tilgjengelig.

**Q: Får jeg 400 Bad Request-feil?**
A: Sjekk at `post_id` eksisterer og at alle parametere er riktig formatert.

**Q: Fungerer ikke WooCommerce-integrasjonen?**
A: Sjekk at WooCommerce er installert og aktivert.

### 📈 Fremtidige Funksjoner

**Q: Kommer det støtte for flere SEO-felter?**
A: Ja, se roadmap-seksjonen for planlagte funksjoner som sosiale medier meta-tagger og schema markup.

**Q: Kommer det bulk-operasjoner?**
A: Ja, bulk-oppdateringer er planlagt for fase 2 av utviklingen.

**Q: Kommer det webhook-støtte?**
A: Ja, webhook-støtte er planlagt for fase 3.

## 🐛 Feilsøking

### Vanlige problemer

1. **401 Unauthorized**

   - Sjekk at Application Password er riktig konfigurert
   - Verifiser at brukeren har `edit_posts`-rettigheter

2. **404 Not Found**

   - Sjekk at plugin-et er aktivt
   - Verifiser at REST API er tilgjengelig

3. **400 Bad Request**
   - Sjekk at `post_id` eksisterer
   - Valider at alle parametere er riktig formatert

### Debugging

Aktiver WordPress debug-logging for å se detaljerte feilmeldinger:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Bidrag

For å bidra til dette plugin-et:

1. Følg WordPress kodestandarder
2. Test endringer grundig
3. Oppdater dokumentasjon
4. Bruk beskrivende commit-meldinger
5. Følg vår [Code of Conduct](CODE_OF_CONDUCT.md)

## 📞 Support

**Utviklet av**: Devora AS  
**Website**: https://devora.no

### 🐛 Rapportere Bugs og Problemer

Hvis du oppdager en bug eller har andre problemer med plugin-et, kan du:

1. **Opprett en GitHub Issue**: Besøk [GitHub Issues](https://github.com/devora-as/rank-math-api-manager/issues) og opprett en ny issue
2. **Inkluder følgende informasjon**:
   - WordPress versjon
   - Plugin versjon
   - PHP versjon
   - Beskrivelse av problemet
   - Steg for å reprodusere problemet
   - Feilmeldinger (hvis noen)
   - Skjermbilder (hvis relevant)

### 🔒 Sikkerhetsproblemer

**Viktig**: Ikke rapporter sikkerhetsproblemer via GitHub Issues. Send dem til **security@devora.no** i stedet.

### 📧 Kontakt

- **Generell support**: Kontakt Devora team via [devora.no](https://devora.no)
- **Sikkerhetsproblemer**: security@devora.no
- **Code of Conduct**: conduct@devora.no

### 📋 Dokumentasjon

- **[Changelog](docs/CHANGELOG-NORWEGIAN.md)**: Se endringslogg for alle versjoner
- **[Security Policy](docs/SECURITY-NORWEGIAN.md)**: Sikkerhetspolicy og rapportering av sårbarheter
- **[Code of Conduct](docs/CODE_OF_CONDUCT-NORWEGIAN.md)**: Felles retningslinjer for bidragsytere
- **[English Documentation](README.md)**: English version of this documentation
- **[English Changelog](CHANGELOG.md)**: English changelog
- **[English Security Policy](docs/SECURITY.md)**: English security policy
- **[English Code of Conduct](CODE_OF_CONDUCT.md)**: English code of conduct

---

**Lisens**: [GPL v3](LICENSE.md) - Devora AS  
**Sist oppdatert**: Juli 2025
