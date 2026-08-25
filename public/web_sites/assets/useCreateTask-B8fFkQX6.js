import{c as a,A as r}from"./index-AlqkgMll.js";import{b as s,u as i}from"./vendor-query-z5Yadg4Z.js";import{h as o}from"./useUpdateTask-C1lWGiWu.js";/**
 * @license lucide-react v0.460.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=a("LayoutGrid",[["rect",{width:"7",height:"7",x:"3",y:"3",rx:"1",key:"1g98yp"}],["rect",{width:"7",height:"7",x:"14",y:"3",rx:"1",key:"6d4xhi"}],["rect",{width:"7",height:"7",x:"14",y:"14",rx:"1",key:"nxv5o0"}],["rect",{width:"7",height:"7",x:"3",y:"14",rx:"1",key:"1bb6yr"}]]);class c{constructor(t){this.taskRepository=t}async execute(t){if(t instanceof FormData)return await this.taskRepository.createTask(t);if(!t.todo||!t.todo.trim())throw r.badRequest("Task title cannot be empty");if(t.todo.trim().length<3)throw r.badRequest("Task title must be at least 3 characters");return await this.taskRepository.createTask({...t,todo:t.todo.trim()})}}const n=new c(o);function m(){const e=s();return i({mutationFn:t=>n.execute(t),onSuccess:()=>{e.invalidateQueries({queryKey:["tasks"]})}})}export{k as L,m as u};
