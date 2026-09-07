import {Blob} from 'node:buffer';
import {config} from '../config.js';

const sendToCrm=async({reference,data,files})=>{
  const payload=new FormData();
  payload.set('reference',reference);
  payload.set('submittedAt',new Date().toISOString());
  payload.set('enquiry',JSON.stringify(data));

  files.forEach((file)=>{
    payload.append('documents',new Blob([file.buffer],{type:file.mimetype}),file.originalname);
  });

  const headers={Accept:'application/json'};
  if(config.crmBearerToken)headers.Authorization=`Bearer ${config.crmBearerToken}`;

  const response=await fetch(config.crmWebhookUrl,{
    method:'POST',
    headers,
    body:payload,
    signal:AbortSignal.timeout(config.crmTimeoutMs)
  });

  const result=await response.json().catch(()=>({}));
  if(!response.ok)throw new Error(result?.error?.message||`CRM rejected the enquiry with status ${response.status}.`);

  return {destination:'crm',accepted:true,confirmationEmailSent:result.confirmationEmailSent===true};
};

export const deliverEnquiry=async(enquiry)=>{
  if(config.enquiryDestination==='mock'){
    return {destination:'mock',accepted:true,stored:false};
  }
  if(config.enquiryDestination==='crm-webhook')return sendToCrm(enquiry);
  throw new Error(`Unsupported ENQUIRY_DESTINATION: ${config.enquiryDestination}`);
};
