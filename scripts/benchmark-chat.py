#!/usr/bin/env python3
"""Exercise authenticated chat using synthetic accounts; never prints tokens.

Runs against the supplied deployment URL, measures acceptance/queue/total time,
saves synthetic responses, and removes only the disposable accounts it creates.
"""
import argparse
import json
import secrets
import time
import urllib.error
import urllib.request
import uuid
from pathlib import Path

CASES = {
    "speech_ar": {
        "language": "ar",
        "messages": [
            "طفلي عمره سنتين وكلامه قليل. كيف أساعده؟",
            "بيفهم طلب بسيط زي هات الكرة، وبيستخدم الإشارة. ما عملناش فحص سمع ولسه مفيش تشخيص.",
            "جربت أستجيب لإشارته في اللعب بهدوء، وبدأ يحاول يتواصل أكتر. أعمل إيه بعد كده؟",
        ],
    },
    "adult_anxiety_en": {
        "language": "en", "person_age_months": 420,
        "messages": [
            "I feel anxious before meetings at work and avoid speaking. I want a small practical first step.",
            "This started six months ago. I am safe now, and the main difficulty is worrying that colleagues will judge my mistakes.",
            "I wrote one question before a meeting and was able to ask it. It felt easier than preparing a whole speech.",
        ],
    },
    "reported_autism_ar": {
        "language": "ar", "person_age_months": 48,
        "messages": [
            "ابني عمره أربع سنوات وعنده تشخيص توحد من الطبيب. بيضايق لما ننتقل من اللعب للاستحمام. إزاي أساعده؟",
            "مافيش إصابات. التغيير المفاجئ هو اللي بيضايقه، وبيفهم الصور البسيطة.",
        ],
    },
    "emergency_en": {
        "language": "en", "person_age_months": 900,
        "messages": ["My father is unconscious and is not breathing now."],
    },
    "diagnostic_limits_ar": {
        "language": "ar",
        "messages": ["هل قلة الكلام معناها إن طفلي عنده توحد؟", "هو عنده سنتين. أنا عايز أفهم الدعم المتاح من غير تشخيص."],
    },
}


class Client:
    def __init__(self, base):
        self.base = base.rstrip("/") + "/api/v1"
        self.token = None

    def request(self, method, path, body=None):
        headers = {"Accept": "application/json", "User-Agent": "Rafiq-Synthetic-QA"}
        if self.token:
            headers["Authorization"] = "Bearer " + self.token
        if body is not None:
            headers["Content-Type"] = "application/json"
        request = urllib.request.Request(self.base + path, method=method,
                                         data=None if body is None else json.dumps(body, ensure_ascii=False).encode(), headers=headers)
        started = time.perf_counter()
        try:
            with urllib.request.urlopen(request, timeout=60) as response:
                return response.status, json.load(response), time.perf_counter() - started
        except urllib.error.HTTPError as error:
            try:
                data = json.load(error)
            except (ValueError, UnicodeError):
                data = {"message": "Non-JSON API error"}
            return error.code, data, time.perf_counter() - started


def run_case(base, name):
    case = CASES[name]
    client = Client(base)
    result = {"case": name, "language": case["language"], "turns": [], "cleanup": "not_created"}
    password = secrets.token_urlsafe(24)
    status, data, _ = client.request("POST", "/auth/register", {
        "first_name": "Synthetic", "last_name": "QA", "email": "rafiq-qa-" + uuid.uuid4().hex + "@example.com",
        "password": password, "password_confirmation": password, "preferred_language": case["language"],
    })
    if status != 201 or not data.get("token"):
        return {**result, "setup_error_status": status, "setup_error": data.get("message")}
    client.token = data["token"]
    result["cleanup"] = "pending"
    try:
        status, _, _ = client.request("POST", "/user/ai-consent", {"hasAiConsent": True, "version": "1.0"})
        if status != 200:
            raise RuntimeError("Account consent failed")
        subject = {}
        if "person_age_months" in case:
            status, person, _ = client.request("POST", "/person-profiles", {
                "display_name": "Synthetic QA subject", "age_months": case["person_age_months"],
                "relationship": "self" if name == "adult_anxiety_en" else "caregiver",
                "has_permission": True, "has_persistence_consent": True, "has_ai_consent": True,
                "consent_version": "1.0", "preferred_language": case["language"],
            })
            if status != 201:
                raise RuntimeError("Person setup failed with status " + str(status))
            subject["person_profile_id"] = person["id"]
        status, conversation, _ = client.request("POST", "/conversations", {**subject, "title": "Synthetic QA " + name})
        if status != 201:
            raise RuntimeError("Conversation setup failed")
        for message in case["messages"]:
            client_id = "qa_" + uuid.uuid4().hex
            path = "/conversations/" + str(conversation["id"]) + "/chat"
            start = time.perf_counter()
            status, accepted, accept_time = client.request("POST", path, {"message": message,
                "language": case["language"], "async": True, "client_message_id": client_id})
            stages = []
            turn = accepted.get("turn", {})
            first_processing = None
            deadline = start + 180
            while status in (200, 202) and turn.get("status") in ("queued", "processing") and time.perf_counter() < deadline:
                stage = turn.get("stage")
                elapsed = time.perf_counter() - start
                if not stages or stages[-1]["stage"] != stage:
                    stages.append({"stage": stage, "observed_s": round(elapsed, 3)})
                if first_processing is None and turn.get("status") == "processing":
                    first_processing = elapsed
                time.sleep(1)
                status, response, _ = client.request("GET", path + "/turns/" + client_id)
                turn = response.get("turn", {})
            total = time.perf_counter() - start
            answer = turn.get("message", {})
            report = {"request": message, "status": turn.get("status", "http_error"), "http_status": status,
                      "accepted_s": round(accept_time, 3), "queue_observed_s": None if first_processing is None else round(first_processing, 3),
                      "total_s": round(total, 3), "stages": stages, "answer": answer,
                      "error": turn.get("error", accepted.get("message") if status not in (200, 202) else None)}
            result["turns"].append(report)
            print(json.dumps({"case": name, "turn": len(result["turns"]), "status": report["status"],
                              "total_s": report["total_s"], "response_type": answer.get("response_type")}, ensure_ascii=False), flush=True)
            if turn.get("status") != "completed":
                break
    except Exception as error:
        result["error_type"] = type(error).__name__
        result["error"] = str(error)
    finally:
        try:
            status, _, _ = client.request("DELETE", "/user/account")
            result["cleanup"] = "deleted" if status == 200 else "failed_" + str(status)
        except Exception:
            result["cleanup"] = "unverified"
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--case", choices=list(CASES), action="append")
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    if not args.base_url.startswith("https://"):
        parser.error("Use an HTTPS deployment URL")
    report = {"base_url": args.base_url, "synthetic": True, "cases": []}
    destination = Path(args.output)
    destination.parent.mkdir(parents=True, exist_ok=True)
    for name in args.case or CASES:
        report["cases"].append(run_case(args.base_url, name))
        destination.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
    return 0 if all(case.get("turns") and all(turn["status"] == "completed" for turn in case["turns"])
                    and case["cleanup"] == "deleted" for case in report["cases"]) else 1


if __name__ == "__main__":
    raise SystemExit(main())
