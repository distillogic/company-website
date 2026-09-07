# DISTILLOGIC website backend

This service receives the **Discuss a Project** form, validates all fields and uploads, and forwards accepted enquiries to a configured destination.

## Local setup

1. Open a terminal in `Distillogic/backend`.
2. Run `npm install`.
3. Copy `.env.example` to `.env`.
4. Keep `ENQUIRY_DESTINATION=mock` during local development.
5. Run `npm run dev`.
6. Open `http://localhost:3000`.

The same Node process serves both the website and `/api/*`, so the browser does not need cross-origin credentials or a public CRM key.

## Safety of mock mode

Mock mode validates the complete request but stores and sends nothing. Production startup deliberately fails while mock mode is selected, preventing a live form from silently discarding genuine enquiries.

## CRM connection

After the CRM API is documented:

1. Confirm its endpoint, authentication method and field names.
2. Adapt `src/services/enquiry-destination.js` if its payload differs from the generic multipart webhook.
3. Set `ENQUIRY_DESTINATION=crm-webhook`.
4. Set `CRM_WEBHOOK_URL` and `CRM_BEARER_TOKEN` in the server's private `.env` file.
5. Never commit `.env` or place the token in frontend JavaScript.

## Production deployment

Run the Node service with a process manager or container and place it behind HTTPS. If a reverse proxy is used, route the website and `/api` to this service and set `TRUST_PROXY=true`. Restrict the server firewall, keep Node dependencies patched, configure backups in the CRM, and add malware scanning before enabling enterprise file uploads.
