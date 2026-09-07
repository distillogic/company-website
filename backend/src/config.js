import 'dotenv/config';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const currentDirectory=path.dirname(fileURLToPath(import.meta.url));

const integer=(value,fallback)=>{
  const parsed=Number.parseInt(value,10);
  return Number.isFinite(parsed)&&parsed>0?parsed:fallback;
};

const boolean=(value)=>String(value).toLowerCase()==='true';

export const config=Object.freeze({
  environment:process.env.NODE_ENV||'development',
  port:integer(process.env.PORT,3000),
  trustProxy:boolean(process.env.TRUST_PROXY),
  siteRoot:path.resolve(currentDirectory,'..','..'),
  enquiryDestination:process.env.ENQUIRY_DESTINATION||'mock',
  crmWebhookUrl:process.env.CRM_WEBHOOK_URL||'',
  crmBearerToken:process.env.CRM_BEARER_TOKEN||'',
  crmTimeoutMs:integer(process.env.CRM_TIMEOUT_MS,10000),
  resendApiKey:process.env.RESEND_API_KEY||'',
  resendFromEmail:process.env.RESEND_FROM_EMAIL||'',
  maxFiles:integer(process.env.MAX_FILES,5),
  maxFileSizeBytes:integer(process.env.MAX_FILE_SIZE_MB,10)*1024*1024,
  maxTotalUploadBytes:integer(process.env.MAX_TOTAL_UPLOAD_MB,25)*1024*1024
});

export const validateProductionConfig=()=>{
  if(config.environment==='production'&&config.enquiryDestination==='mock'){
    throw new Error('ENQUIRY_DESTINATION cannot be mock in production. Configure the CRM connector first.');
  }
  if(config.enquiryDestination==='crm-webhook'&&!config.crmWebhookUrl){
    throw new Error('CRM_WEBHOOK_URL is required when ENQUIRY_DESTINATION=crm-webhook.');
  }
  if(config.environment==='production'&&(!config.resendApiKey||!config.resendFromEmail)){
    throw new Error('RESEND_API_KEY and RESEND_FROM_EMAIL are required in production.');
  }
};
