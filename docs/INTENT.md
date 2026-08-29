# DPIK Tadbir: Intent

## [INTENT-01] Purpose & Mission
DPIK Tadbir is an AI-assisted company management command center built for the Managing Director (MD) and executive leadership to oversee multi-project health, monitor staff capacity/delegation, and handle executive communications (Outlook/Gmail inbox triage, summaries, drafting) with strict safety boundaries and human-in-the-loop approval.

## [INTENT-02] Primary Problem Space
1. **Executive Fragmentation**: The Managing Director has to manually jump between email client (Outlook/Gmail), project ticketing boards (Tugas), and private personal scratchpads to know what is happening across the firm.
2. **Context Loss Between Comms & Actions**: Email conversations often contain critical action items, project blockers, and decisions that get lost instead of being turned directly into structured project tickets, personal tasks, or notes.
3. **Overload & Blind Spots**: Bottlenecks across departmental positions and project deadlines remain hidden until milestone failures occur.
4. **AI Agency Risks**: Fully autonomous agents that send emails or mutate company databases without human oversight risk hallucinations and unauthorized mutations.

## [INTENT-03] Core Operating Principles
1. **Evidence-Based Oversight**: The AI agent never hallucinates metrics or statuses; all answers cite concrete database records (tickets, positions, logs) or email message IDs.
2. **Explicit Write Safety**: AI proposes mutations (reassigning tickets, saving summaries to personal notes, sending email drafts). The human operator explicitly approves before execution.
3. **Dual Surface (Conversation + Visual UI)**: Fast conversational inquiries for broad executive synthesis, backed by full visual Filament tables and inbox lists for detailed review.
4. **Zero Ambient State / Stateless MCP**: Email and tool integrations interact via standardized Model Context Protocol (MCP) servers with clean credential isolation.

## [INTENT-04] Anti-Goals (What This Product Is NOT)
1. **Not a Generic Public Chatbot**: This is an internal executive workstation tailored specifically to company operations and personal inbox management.
2. **Not an Autonomous Outbound Bot**: It will never send an email or delete project tickets in the background without explicit human authorization.
3. **Not a Multi-Tenant Public SaaS**: Built as a single-enterprise / executive management tool with local-first and self-hosted capabilities.
